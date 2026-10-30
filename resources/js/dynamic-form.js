/**
 * Client-side display conditions of dynamic forms (same logic as
 * FormSchema::isVisible on the server, which stays the reference).
 */
window.linkDynamicForm = (config) => ({
    answers: config.answers,
    conditions: config.conditions || {},

    visible(key, depth = 0) {
        const condition = this.conditions[key];

        if (!condition) {
            return true;
        }

        if (depth > 50 || !this.visible(condition.field, depth + 1)) {
            return false;
        }

        const raw = this.answers ? this.answers[condition.field] : null;
        const given = (Array.isArray(raw) ? raw : [raw])
            .filter((value) => value !== null && value !== undefined && value !== '')
            .map(String);
        const expected = (Array.isArray(condition.value) ? condition.value : [condition.value]).map(String);
        const matches = given.some((value) => expected.includes(value));

        return condition.operator === 'not_equals' ? !matches : matches;
    },
});

/**
 * Resumable, chunked upload of one file at a time (§4.6). A file the server
 * already has is not sent again; after a network failure the upload resumes
 * from the last byte received.
 */
window.linkChunkedUpload = (config) => ({
    queue: [],
    busy: false,
    reconnecting: false,

    async pick(event) {
        const files = Array.from(event.target.files || []);
        event.target.value = '';

        for (const file of files) {
            this.queue.push({ file, name: file.name, progress: 0, error: null, done: false });
        }

        if (!this.busy) {
            await this.run();
        }
    },

    async run() {
        this.busy = true;

        for (const item of this.queue) {
            if (!item.done && !item.error) {
                await this.send(item);
            }
        }

        this.busy = false;
        this.queue = this.queue.filter((item) => !item.done);
        await config.onComplete();
    },

    async retry(item) {
        item.error = null;

        if (!this.busy) {
            await this.run();
        }
    },

    async send(item) {
        try {
            const sha256 = await this.fingerprint(item.file);
            const init = await this.request(config.initUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ field: config.field, name: item.file.name, size: item.file.size, sha256 }),
            });
            let offset = init.received_bytes;

            while (offset < item.file.size && init.status !== 'complete') {
                const chunk = item.file.slice(offset, offset + config.chunkSize);
                const result = await this.request(init.chunk_url, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/octet-stream', 'X-Upload-Offset': String(offset) },
                    body: chunk,
                });
                offset = result.received_bytes;
                item.progress = Math.round((offset / item.file.size) * 100);
            }

            item.progress = 100;
            item.done = true;
        } catch (error) {
            item.error = error.message || config.messages.network;
        }
    },

    async request(url, options, attempt = 0) {
        let response;

        try {
            response = await fetch(url, {
                ...options,
                credentials: 'same-origin',
                headers: { ...options.headers, 'X-CSRF-TOKEN': config.csrf, Accept: 'application/json' },
            });
        } catch (networkError) {
            if (attempt < 5) {
                this.reconnecting = true;
                await new Promise((resolve) => setTimeout(resolve, 1000 * 2 ** attempt));

                return this.request(url, options, attempt + 1);
            }

            this.reconnecting = false;
            throw new Error(config.messages.failed);
        }

        this.reconnecting = false;

        const data = await response.json().catch(() => ({}));

        // 409: the server already has more (or less) than we thought: resume from its count.
        if (response.status === 409 && typeof data.received_bytes === 'number') {
            return data;
        }

        if (!response.ok) {
            throw new Error(data.message || config.messages.failed);
        }

        return data;
    },

    async fingerprint(file) {
        if (!window.crypto || !window.crypto.subtle) {
            return null;
        }

        const digest = await window.crypto.subtle.digest('SHA-256', await file.arrayBuffer());

        return Array.from(new Uint8Array(digest)).map((b) => b.toString(16).padStart(2, '0')).join('');
    },
});
