@props(['fields', 'errors'])

{{-- Fields of a dynamic form. The parent sets x-data="linkDynamicForm(...)". --}}
<div {{ $attributes }}>
    @foreach ($fields as $field)
        <x-dynamic.field :field="$field" :errors="$errors" />
    @endforeach
</div>
