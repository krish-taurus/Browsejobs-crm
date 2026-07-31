<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'taurus' => [
        'base_url' => env('THETAURUS_API_BASE'),
        'token' => env('THETAURUS_API_TOKEN'),
    ],

    'lms' => [
        // Filesystem path of the BrowseJobs LMS Laravel app — used to run its
        // artisan commands (funnel:advance) from the CRM's Batch Funnel page.
        'path' => env('LMS_APP_PATH', 'C:\\xampp\\htdocs\\Browsejobs-lms-main\\apps\\api'),
        // CLI php executable. PHP_BINARY is Apache's binary in web requests,
        // so the CLI path must be explicit (or discoverable on PATH).
        'php_binary' => env('LMS_PHP_BINARY', 'C:\\xampp\\php\\php.exe'),
        // Public "watch the masterclass" page (simulated-live daily showing).
        // WhatsApped + emailed to every lead the moment they turn Interested.
        'masterclass_watch_url' => env('LMS_MASTERCLASS_WATCH_URL', 'http://localhost:3000/masterclass/watch'),
        // Human-readable daily show time for the invite message — keep in sync
        // with the LMS's FUNNEL_MASTERCLASS_WATCH_TIME (default 20:00).
        'masterclass_show_time_label' => env('MASTERCLASS_SHOW_TIME_LABEL', '8:00 PM'),
    ],

    'google_drive' => [
        // Path (relative to base_path) to the service-account JSON key file.
        'key_file' => env('GOOGLE_DRIVE_KEY_FILE', 'storage/app/google/service-account.json'),
        // ID of a Drive folder shared with the service account — new folders/files land here.
        'root_folder_id' => env('GOOGLE_DRIVE_ROOT_FOLDER_ID'),

        // OAuth mode (recommended for personal Gmail): the user connects their own Google
        // account and files upload into their Drive. Takes priority over the service account.
        'oauth' => [
            'client_id' => env('GOOGLE_OAUTH_CLIENT_ID'),
            'client_secret' => env('GOOGLE_OAUTH_CLIENT_SECRET'),
            'redirect' => env('GOOGLE_OAUTH_REDIRECT_URI', rtrim((string) env('APP_URL', 'http://localhost'), '/').'/file-manager/google/callback'),
        ],
    ],

    'whatsapp' => [
        // Meta WhatsApp Cloud API.
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
        'default_country_code' => env('WHATSAPP_DEFAULT_COUNTRY_CODE', '91'),
        // Shared secret Meta echoes back when verifying the webhook Callback URL.
        'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
        // Meta-approved template names (business-initiated messages outside the
        // 24h session window). Sends fall back to session text until approved.
        'templates' => [
            'masterclass_invite' => env('WA_TPL_MASTERCLASS_INVITE', 'bj_masterclass_invite'),
            'masterclass_followup' => env('WA_TPL_MASTERCLASS_FOLLOWUP', 'bj_masterclass_followup'),
        ],
    ],

    'linkedin' => [
        'client_id' => env('LINKEDIN_CLIENT_ID'),
        'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
        // Must exactly match an "Authorized redirect URL" in the LinkedIn app.
        'redirect' => env('LINKEDIN_REDIRECT_URI', rtrim((string) env('APP_URL', 'http://localhost'), '/').'/social-accounts/linkedin/callback'),
        // Org-page scopes need the "Community Management API" product approved on the app.
        'scopes' => env('LINKEDIN_SCOPES', 'openid profile email r_organization_social rw_organization_admin r_organization_admin'),
        'api_version' => env('LINKEDIN_API_VERSION', '202401'),
    ],

    // Shared secret the website sends (X-Lead-Token header) to the public lead-capture endpoint.
    'lead_capture_token' => env('LEAD_CAPTURE_TOKEN'),

    'caller_digital' => [
        // Caller.Digital Campaigns API — outbound AI voice calls.
        'base_url' => env('CALLER_DIGITAL_BASE_URL', 'https://35-223-76-161.nip.io'),
        'email' => env('CALLER_DIGITAL_EMAIL'),
        'password' => env('CALLER_DIGITAL_PASSWORD'),
        'api_key' => env('CALLER_DIGITAL_API_KEY'),
        'agent_id' => env('CALLER_DIGITAL_AGENT_ID'),          // default AI agent UUID
        'from_number' => env('CALLER_DIGITAL_FROM_NUMBER'),     // E.164 caller ID
        'default_language' => env('CALLER_DIGITAL_LANGUAGE', 'english'),
        'webhook_secret' => env('CALLER_DIGITAL_WEBHOOK_SECRET'), // shared secret to verify incoming webhooks
        // Automatically place an AI call the moment a lead is generated.
        'auto_call' => env('CALLER_DIGITAL_AUTO_CALL', true),
        // Max AI calls allowed per day (0 = unlimited). Guards against runaway cost.
        'daily_cap' => (int) env('CALLER_DIGITAL_DAILY_CAP', 0),
    ],

    // Meeting intelligence: Google Drive folder where Meet saves recordings +
    // transcripts. Share the folder with the service-account email, then put
    // its folder id here. meetings:analyze picks up new transcripts from it.
    'meetings' => [
        'transcripts_folder_id' => env('MEET_TRANSCRIPTS_FOLDER_ID'),
    ],

    // Daily standup reminder (standup:remind, scheduled in routes/console.php).
    'standup' => [
        'meet_link' => env('STANDUP_MEET_LINK'),
        'time' => env('STANDUP_TIME', '9:30 AM'),
    ],

    // AI lead analysis — pick any provider(s) by adding its API key. Providers
    // without a key simply don't appear in the UI (graceful no-op pattern).
    'ai_analysis' => [
        'anthropic' => [
            'label' => 'Claude (Anthropic)',
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('ANTHROPIC_MODEL', 'claude-opus-4-8'),
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
        ],
        'openai' => [
            'label' => 'ChatGPT (OpenAI)',
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        ],
        'kimi' => [
            'label' => 'Kimi (Moonshot AI)',
            'api_key' => env('KIMI_API_KEY'),
            'model' => env('KIMI_MODEL', 'kimi-latest'),
            'base_url' => env('KIMI_BASE_URL', 'https://api.moonshot.ai/v1'),
        ],
    ],

];
