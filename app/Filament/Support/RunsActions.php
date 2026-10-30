<?php

namespace App\Filament\Support;

use App\Exceptions\ActionFailed;
use Closure;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Runs a domain Action from a Filament action or page and turns business
 * rule refusals into a visible notification instead of an error page.
 */
class RunsActions
{
    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     *
     * @throws Halt
     */
    public static function attempt(Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (ActionFailed $exception) {
            self::fail($exception->getMessage());
        } catch (ValidationException $exception) {
            self::fail((string) collect($exception->errors())->flatten()->first());
        } catch (AuthorizationException) {
            self::fail(__('public.not_allowed'));
        }
    }

    /**
     * @throws Halt
     */
    private static function fail(string $message): never
    {
        Notification::make()->danger()->title($message)->persistent()->send();

        throw (new Halt)->rollBackDatabaseTransaction();
    }
}
