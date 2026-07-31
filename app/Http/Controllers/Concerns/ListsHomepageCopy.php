<?php

namespace App\Http\Controllers\Concerns;

/**
 * The homepage's editable copy, section by section, in the order the sections
 * appear on the live page. Mirrors HOMEPAGE_DEFAULTS in the website's
 * src/content/homepage.ts — a key added there is added here to expose it, and
 * HomepageCopyMatchesSiteTest fails the build if the two ever drift apart.
 *
 * Field shape: key => [label, type, default] where type is 'text' (one line)
 * or 'area' (paragraph) and default is the wording the site ships with. The
 * editor prefills every box with the default so the founder edits real text
 * instead of guessing; a value equal to the default is not stored, so the page
 * keeps following the shipped copy until it is genuinely changed.
 */
trait ListsHomepageCopy
{
    /**
     * @return array<string, array{icon: string, note: string, fields: array<string, array{0: string, 1: string, 2: string}>}>
     */
    protected function homepageSections(): array
    {
        return [

            'Hero' => [
                'icon' => 'ti-typography',
                'note' => 'The first screen — the huge headline visitors land on.',
                'fields' => [
                    'hero.kicker' => ['Small line above the headline', 'text', 'BrowseJobs · The Career Engine'],
                    'hero.headline_start' => ['Headline — first part', 'text', 'Six months from'],
                    'hero.headline_highlight' => ['Headline — highlighted words (blue gradient)', 'text', '“still searching”'],
                    'hero.headline_end' => ['Headline — last part', 'text', 'to signed.'],
                    'hero.subline' => ['Paragraph under the headline', 'area', 'An AI tutor that never sleeps. Mock interviews that call your phone. A job radar that hunts while you study.'],
                    'hero.subline_strong' => ['Bold closing sentence', 'text', 'You bring the effort — we bring the offer.'],
                    'hero.cta_primary' => ['Main button text', 'text', 'Book the free masterclass'],
                    'hero.cta_secondary' => ['Secondary link text', 'text', 'Watch it work'],
                    'hero.cta_note' => ['Small note under the buttons', 'text', 'Free seat · No card · 20,000+ students before you'],
                ],
            ],

            'Manifesto' => [
                'icon' => 'ti-quote',
                'note' => 'The big scroll-reveal statement and the four "machine" cards.',
                'fields' => [
                    'manifesto.line1' => ['Statement — first line', 'area', 'Colleges hand you a degree. Nobody hands you a career.'],
                    'manifesto.line2' => ['Statement — gradient line', 'text', 'So we built a machine.'],
                    'manifesto.verb1_title' => ['Card 1 — title', 'text', 'Studies'],
                    'manifesto.verb1_body' => ['Card 1 — description', 'text', '~50 real interview transcripts, every day'],
                    'manifesto.verb2_title' => ['Card 2 — title', 'text', 'Teaches'],
                    'manifesto.verb2_body' => ['Card 2 — description', 'text', 'exactly what companies ask right now'],
                    'manifesto.verb3_title' => ['Card 3 — title', 'text', 'Drills'],
                    'manifesto.verb3_body' => ['Card 3 — description', 'text', 'you until pressure feels normal'],
                    'manifesto.verb4_title' => ['Card 4 — title', 'text', 'Hunts'],
                    'manifesto.verb4_body' => ['Card 4 — description', 'text', 'jobs with you until you sign an offer'],
                ],
            ],

            'The Path (7 steps)' => [
                'icon' => 'ti-route',
                'note' => 'The side-scrolling journey from first free call to accepted offer.',
                'fields' => [
                    'path.kicker' => ['Small label above the heading', 'text', 'The path'],
                    'path.headline_start' => ['Heading — first part', 'text', 'Three free steps before'],
                    'path.headline_highlight' => ['Heading — green words', 'text', 'you pay a rupee.'],
                    'path.body' => ['Paragraph under the heading', 'area', 'Keep scrolling — the page walks the whole journey with you, from your first free call to an accepted offer.'],
                    'path.step1_title' => ['Step 1 — title', 'text', 'Free counselling'],
                    'path.step1_body' => ['Step 1 — description', 'text', 'A written Career Analysis Report — whether you join or not.'],
                    'path.step2_title' => ['Step 2 — title', 'text', 'Free live masterclass'],
                    'path.step2_body' => ['Step 2 — description', 'text', 'See the method live: how the syllabus is reverse-engineered.'],
                    'path.step3_title' => ['Step 3 — title', 'text', 'Free 7-hour bootcamp'],
                    'path.step3_body' => ['Step 3 — description', 'text', 'Sit a real teaching day of live Python before you decide.'],
                    'path.step4_title' => ['Step 4 — title', 'text', 'Paid batch'],
                    'path.step4_body' => ['Step 4 — description', 'text', 'Live, instructor-led. ₹30,000 — EMI 3 × ₹10,000.'],
                    'path.step5_title' => ['Step 5 — title', 'text', 'AI coach'],
                    'path.step5_body' => ['Step 5 — description', 'text', 'PRI, mastery map, and one clear next best action, daily.'],
                    'path.step6_title' => ['Step 6 — title', 'text', 'Mock interviews'],
                    'path.step6_body' => ['Step 6 — description', 'text', 'Voice mocks scored on real interview data, with a gap report.'],
                    'path.step7_title' => ['Step 7 — title', 'text', 'Placement'],
                    'path.step7_body' => ['Step 7 — description', 'text', 'Your profile worked until you accept an offer.'],
                    'path.finale_title' => ['Final card — title', 'text', 'Offer accepted.'],
                    'path.finale_body' => ['Final card — description', 'area', 'The placement fee starts only here — paid from the salary the offer brings.'],
                ],
            ],

            'Feature scenes' => [
                'icon' => 'ti-device-desktop',
                'note' => 'The three dark sections: AI Tutor, Voice Mock Lab, Job Radar.',
                'fields' => [
                    'scene1.kicker' => ['AI Tutor — small label', 'text', '01 · AI Tutor'],
                    'scene1.title_line1' => ['AI Tutor — heading line 1', 'text', 'Stuck at 2am?'],
                    'scene1.title_line2' => ['AI Tutor — heading line 2', 'text', 'So is your tutor.'],
                    'scene1.title_highlight' => ['AI Tutor — highlighted word', 'text', 'Awake.'],
                    'scene1.body' => ['AI Tutor — paragraph', 'area', 'Every doubt answered in seconds, in your context, from your syllabus. Students ask it 400+ questions a course — because it never judges, never sleeps, never says \'ask me tomorrow\'.'],
                    'scene2.kicker' => ['Mock Lab — small label', 'text', '02 · Voice Mock Lab'],
                    'scene2.title_line1' => ['Mock Lab — heading', 'text', 'The interviewer that'],
                    'scene2.title_highlight' => ['Mock Lab — highlighted words', 'text', 'calls you first.'],
                    'scene2.body' => ['Mock Lab — paragraph', 'area', 'A voice AI rings your phone, runs a real technical round, and scores every answer — communication, depth, confidence. By interview #12, the real thing feels like a rerun.'],
                    'scene3.kicker' => ['Job Radar — small label', 'text', '03 · Job Radar'],
                    'scene3.title_line1' => ['Job Radar — heading', 'text', 'Openings hunted'],
                    'scene3.title_highlight' => ['Job Radar — highlighted words', 'text', 'while you sleep.'],
                    'scene3.body' => ['Job Radar — paragraph', 'area', 'Live roles from LinkedIn and Naukri, matched to your skill graph and refreshed daily. When you\'re ready, the interviews are already waiting.'],
                ],
            ],

            'Everything included' => [
                'icon' => 'ti-layout-grid',
                'note' => 'The heading above the grid of feature tiles.',
                'fields' => [
                    'bento.kicker' => ['Small label above the heading', 'text', 'Everything included'],
                    'bento.headline' => ['Heading', 'text', 'One fee. The whole machine.'],
                    'bento.body' => ['Paragraph', 'area', 'No add-on pricing for the things that get you hired. Every tool below ships with every track.'],
                ],
            ],

            'Programs' => [
                'icon' => 'ti-school',
                'note' => 'The heading above the four course cards. Course details are edited under Website → Pages.',
                'fields' => [
                    'programs.kicker' => ['Small label above the heading', 'text', 'Programs'],
                    'programs.headline' => ['Heading', 'text', 'Four live tracks. Each one rebuilt monthly.'],
                    'programs.body_start' => ['Paragraph — first part', 'text', 'Only four — because'],
                    'programs.body_strong' => ['Paragraph — bold words', 'text', 'demand decides what we teach.'],
                    'programs.body_end' => ['Paragraph — last part', 'area', 'We run tracks only where the market is actively hiring, and the live opening counts below prove it.'],
                ],
            ],

            'Free career report' => [
                'icon' => 'ti-file-analytics',
                'note' => 'The dark resume-scan section.',
                'fields' => [
                    'report.kicker' => ['Small label above the heading', 'text', 'Free career report · unbiased'],
                    'report.headline_start' => ['Heading — first part', 'text', 'Where does the market say'],
                    'report.headline_highlight' => ['Heading — highlighted word', 'text', 'you'],
                    'report.headline_end' => ['Heading — last part', 'text', 'should go next?'],
                    'report.body_start' => ['Paragraph — first part', 'area', 'Paste your resume, register once, and get a genuine direction report: what\'s working, what\'s missing, and which path fits your skills —'],
                    'report.body_strong' => ['Paragraph — bold words', 'text', 'even when it\'s one we don\'t teach.'],
                    'report.body_end' => ['Paragraph — last part', 'text', 'Your resume is analysed and discarded, never stored.'],
                    'report.cta' => ['Button text', 'text', 'Get my free report'],
                    'report.note' => ['Small note under the button', 'text', 'The analysis runs only after registration.'],
                ],
            ],

            'Fees' => [
                'icon' => 'ti-receipt-rupee',
                'note' => 'The two fee cards. The ₹30,000 figure and the worked example rows stay in code.',
                'fields' => [
                    'fees.kicker' => ['Small label above the heading', 'text', 'The fee structure'],
                    'fees.headline_start' => ['Heading — first part', 'text', 'Two fees. Both in writing.'],
                    'fees.headline_highlight' => ['Heading — gradient words', 'text', 'One only after you\'re hired.'],
                    'fees.reg_label' => ['Registration card — label', 'text', '01 · Registration'],
                    'fees.reg_note' => ['Registration card — description', 'area', 'Payable only after the free masterclass and the free 7-hour bootcamp.'],
                    'fees.reg_badge1' => ['Registration card — badge 1', 'text', 'One payment'],
                    'fees.reg_badge2' => ['Registration card — badge 2', 'text', 'EMI · 3 × ₹10,000'],
                    'fees.reg_guarantee' => ['Registration card — guarantee line', 'area', '30-day money-back guarantee — any reason, full refund, in writing.'],
                    'fees.place_label' => ['Placement card — label', 'text', '02 · Placement fee'],
                    'fees.place_body_start' => ['Placement card — first part', 'text', 'Your first 3 months\' CTC, due'],
                    'fees.place_body_strong' => ['Placement card — bold words', 'text', 'only after you accept an offer'],
                    'fees.place_body_end' => ['Placement card — last part', 'area', '— paid as 6 monthly EMIs from your new salary. Your ₹30,000 registration is adjusted inside it.'],
                    'fees.example_label' => ['Worked example — label', 'text', 'Worked example · ₹12 LPA offer'],
                ],
            ],

            'Honesty doctrine' => [
                'icon' => 'ti-shield-check',
                'note' => 'The "verify us" steps and the two promise lists.',
                'fields' => [
                    'honesty.kicker' => ['Small label above the heading', 'text', 'The honesty doctrine'],
                    'honesty.headline_start' => ['Heading — first part', 'text', 'Don\'t trust us.'],
                    'honesty.headline_highlight' => ['Heading — blue words', 'text', 'Verify us.'],
                    'honesty.body' => ['Paragraph', 'area', 'Fifteen minutes on Naukri is enough to check everything we claim. Here\'s how.'],
                    'honesty.step1_time' => ['Step 1 — time badge', 'text', '5 min'],
                    'honesty.step1_title' => ['Step 1 — title', 'text', 'Count the demand'],
                    'honesty.step1_body' => ['Step 1 — description', 'area', 'Open Naukri → search the role + your city → filter “Last 7 days”. If hiring is real, you\'ll see it in the number.'],
                    'honesty.step2_time' => ['Step 2 — time badge', 'text', '5 min'],
                    'honesty.step2_title' => ['Step 2 — title', 'text', 'Read 5 job descriptions'],
                    'honesty.step2_body' => ['Step 2 — description', 'area', 'Note the skills that repeat, then compare them against any institute\'s syllabus — ours included.'],
                    'honesty.step3_time' => ['Step 3 — time badge', 'text', '5 min'],
                    'honesty.step3_title' => ['Step 3 — title', 'text', 'Pressure-test everyone'],
                    'honesty.step3_body' => ['Step 3 — description', 'area', 'Three questions separate honest institutes from the rest. Ask them everywhere.'],
                    'honesty.promise_title' => ['Promises card — title', 'text', 'What we promise in writing'],
                    'honesty.promise1' => ['Promise 1', 'text', 'Every commitment you get from us is in writing.'],
                    'honesty.promise2' => ['Promise 2', 'text', 'Three full steps are free before you pay a rupee.'],
                    'honesty.promise3' => ['Promise 3', 'text', 'The placement fee is due only after you accept an offer.'],
                    'honesty.promise4' => ['Promise 4', 'text', '30-day money-back guarantee — any reason, full refund.'],
                    'honesty.promise5' => ['Promise 5', 'text', 'Every counselling call is recorded & AI-monitored.'],
                    'honesty.never_title' => ['"Never" card — title', 'text', 'What we will never tell you'],
                    'honesty.never1' => ['Never 1', 'text', '“We guarantee you a job.” Nobody honestly can — the market decides.'],
                    'honesty.never2' => ['Never 2', 'text', '“We\'ll adjust your experience.” We never fabricate employment.'],
                    'honesty.never3' => ['Never 3', 'text', 'A fixed salary promise.'],
                    'honesty.never4' => ['Never 4', 'text', 'That hiring is certain, or that there\'s a shortcut.'],
                ],
            ],

            'Success stories' => [
                'icon' => 'ti-trophy',
                'note' => 'The heading above the student story cards.',
                'fields' => [
                    'stories.kicker' => ['Small label above the heading', 'text', 'Success stories'],
                    'stories.headline_start' => ['Heading — first part', 'text', 'From stuck'],
                    'stories.headline_highlight' => ['Heading — green words', 'text', '→ hired.'],
                    'stories.body' => ['Paragraph', 'area', 'Where learners started, the package and company they landed, the rounds they cleared — and the exact question the interview threw at them.'],
                    'stories.disclaimer' => ['Disclaimer under the cards', 'area', 'Based on BrowseJobs internal data. Historical figures — not a promise of individual outcome. Cards marked “Sample” are illustrative — real, consented student stories replace them as they\'re published.'],
                ],
            ],

            'Reviews' => [
                'icon' => 'ti-star',
                'note' => 'The heading above the scrolling review strip.',
                'fields' => [
                    'reviews.kicker' => ['Small label above the heading', 'text', 'In their words'],
                    'reviews.headline' => ['Heading', 'text', 'The people who did it, on the record.'],
                    'reviews.body' => ['Paragraph', 'area', 'Real reviews from Google, JustDial and verified students — never a fabricated one.'],
                ],
            ],

            'Market intelligence' => [
                'icon' => 'ti-chart-line',
                'note' => 'The dark live-signal board. The skills themselves come from the market feed.',
                'fields' => [
                    'intel.kicker' => ['Small label above the heading', 'text', 'The intelligence board'],
                    'intel.headline_start' => ['Heading — first part', 'text', 'Live market signal,'],
                    'intel.headline_highlight' => ['Heading — blue words', 'text', 'read by the engine.'],
                    'intel.rising_label' => ['"Heating up" column label', 'text', 'Heating up — learn these now'],
                    'intel.cooling_label' => ['"Cooling down" column label', 'text', 'Cooling down — don\'t bet a career on these'],
                ],
            ],

            'The numbers' => [
                'icon' => 'ti-123',
                'note' => 'The three big counters. The figures themselves stay in code — these are their captions.',
                'fields' => [
                    'numbers.kicker' => ['Small label above the numbers', 'text', 'The receipts'],
                    'numbers.stat1_label' => ['Caption under 20,000+', 'text', 'students trained'],
                    'numbers.stat2_label' => ['Caption under 98%', 'text', 'success rate among students who attend interviews'],
                    'numbers.stat3_label' => ['Caption under 42 LPA', 'text', 'highest package (₹)'],
                    'numbers.footnote_start' => ['Footnote — first part', 'area', 'Every number above is backed by verifiable student records —'],
                    'numbers.footnote_highlight' => ['Footnote — green words', 'text', 'scan any certificate\'s QR to check us.'],
                ],
            ],

            'Closing call-to-action' => [
                'icon' => 'ti-flag',
                'note' => 'The last dark screen before the footer.',
                'fields' => [
                    'final.headline_start' => ['Heading — first line', 'text', 'The offer letter'],
                    'final.headline_highlight' => ['Heading — gradient line', 'text', 'already has a date.'],
                    'final.body' => ['Paragraph', 'area', 'One free masterclass decides if this is for you. 45 minutes. Zero pressure.'],
                    'final.cta' => ['Button text', 'text', 'Claim my free seat →'],
                    'final.note' => ['Small note under the button', 'text', 'Next batch closes Sunday midnight'],
                ],
            ],
        ];
    }

    /**
     * Every valid copy key, flattened.
     *
     * @return array<int, string>
     */
    protected function homepageCopyKeys(): array
    {
        return array_keys($this->homepageDefaults());
    }

    /**
     * key => the wording the site ships with.
     *
     * @return array<string, string>
     */
    protected function homepageDefaults(): array
    {
        $defaults = [];
        foreach ($this->homepageSections() as $section) {
            foreach ($section['fields'] as $key => $field) {
                $defaults[$key] = $field[2];
            }
        }

        return $defaults;
    }
}
