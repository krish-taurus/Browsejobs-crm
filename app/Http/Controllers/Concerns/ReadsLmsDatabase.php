<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\QueryException;
use Illuminate\View\View;
use PDOException;

/**
 * Shared guard for pages that read the BrowseJobs LMS database.
 * If that database is unreachable (LMS down, credentials changed),
 * render a friendly notice instead of a 500 page.
 */
trait ReadsLmsDatabase
{
    protected function renderLmsPage(callable $callback): View
    {
        try {
            return $callback();
        } catch (QueryException|PDOException $e) {
            report($e);

            return view('lms.unavailable');
        }
    }
}
