<?php

test('the application responds', function () {
    // "/" no longer renders Laravel's welcome page — the CRM root redirects to
    // the login screen (or the dashboard when signed in). See RootRedirectTest.
    $this->get('/')->assertRedirect();
});
