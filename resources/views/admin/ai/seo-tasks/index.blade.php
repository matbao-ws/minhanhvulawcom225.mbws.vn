@extends('admin.layouts.app')

@section('title', __('ai.tasks.title'))

@push('styles')
    <style>
        /* The theme's own muted grey is too faint for sentences people must read: these are darker. */
        .seo-work, #seoFixModal { --seo-text: #1d2939; --seo-muted: #475467; --seo-ai-bg: #eef4ff; --seo-ai-text: #2f5f9e; --seo-why-bg: #eaf2ff; --seo-why-line: #2563c9; --seo-advice: #6d4bd8; --seo-advice-line: #d9cffb; --seo-low_ctr: #b26b00; --seo-striking_distance: #2f5f9e; --seo-declining: #c5221f; --seo-on_page: #5b6b80; --seo-intent_mismatch: #7a3fc4; color: var(--seo-text); }
        [data-bs-theme="dark"] .seo-work, [data-bs-theme="dark"] #seoFixModal { --seo-text: #f2f5f9; --seo-muted: #cfd8e3; --seo-ai-bg: #1f3150; --seo-ai-text: #a9c8f5; --seo-why-bg: rgba(37, 99, 201, .18); --seo-why-line: #8fb5ef; --seo-advice: #b9a5f6; --seo-advice-line: #4b3f7a; --seo-low_ctr: #f0b44c; --seo-striking_distance: #8fb5ef; --seo-declining: #ff8f88; --seo-on_page: #a9b6c8; --seo-intent_mismatch: #c7a3f5; }

        .seo-muted { color: var(--seo-muted) !important; }
        /* Secondary text is read, not skimmed: no hairline weights. */
        .seo-work small, .seo-work .small, .seo-rec__evidence, .seo-rec__impact span, .seo-card__label, .seo-card__trend span,
        .seo-score__source, .seo-task__plan-note, .seo-task__chip small, .seo-task__metrics dt, .seo-outcome__meta, #seoFixModal .seo-fix__block-help,
        #seoFixModal .seo-fix__counter, #seoFixModal .seo-fix__note, #seoFixModal .seo-fix__option small { font-weight: 500; }

        /* Buttons: one strong primary, neutral secondaries (Google Ads style) instead of the theme's teal outline. */
        .seo-work .btn, #seoFixModal .btn { font-weight: 700; }
        .seo-btn-ghost { --bs-btn-color: #1d2939; --bs-btn-bg: #fff; --bs-btn-border-color: #c3cad5; --bs-btn-hover-color: #101828; --bs-btn-hover-bg: #f2f4f7; --bs-btn-hover-border-color: #98a2b3; --bs-btn-active-color: #101828; --bs-btn-active-bg: #e4e7ec; --bs-btn-active-border-color: #98a2b3; --bs-btn-focus-shadow-rgb: 152, 162, 179; }
        [data-bs-theme="dark"] .seo-btn-ghost { --bs-btn-color: #f2f5f9; --bs-btn-bg: transparent; --bs-btn-border-color: #5b6b80; --bs-btn-hover-color: #fff; --bs-btn-hover-bg: rgba(255, 255, 255, .08); --bs-btn-hover-border-color: #8796ab; --bs-btn-active-color: #fff; --bs-btn-active-bg: rgba(255, 255, 255, .14); --bs-btn-active-border-color: #8796ab; }
        .seo-work .btn-link { font-weight: 700; color: var(--seo-ai-text); }
        .seo-tabs .nav-link { color: var(--seo-muted); }
        .seo-help { display: inline; margin-left: .1rem; font-size: .9rem; line-height: 1; color: var(--seo-muted); cursor: help; vertical-align: -.1em; }
        .seo-help:hover, .seo-help:focus-visible { color: var(--bs-primary); outline: 0; }

        /* Overview: score card + one card per figure */
        .seo-overview { display: grid; grid-template-columns: minmax(0, 1.9fr) repeat(4, minmax(0, 1fr)); gap: .85rem; }
        .seo-card { padding: 1rem 1.05rem; border: 1px solid var(--bs-border-color); border-radius: 1rem; background: var(--bs-body-bg); box-shadow: 0 .2rem .8rem rgba(16, 24, 40, .04); }
        .seo-card--score { display: flex; gap: 1rem; align-items: center; }
        .seo-score__ring { --ring: var(--bs-success); position: relative; display: grid; flex: 0 0 92px; width: 92px; height: 92px; place-items: center; border-radius: 50%; background: conic-gradient(var(--ring) calc(var(--score) * 1%), var(--bs-tertiary-bg) 0); }
        .seo-score__ring::before { position: absolute; inset: 9px; border-radius: 50%; background: var(--bs-body-bg); content: ""; }
        .seo-score__ring--warning { --ring: #e8a317; }
        .seo-score__ring--danger { --ring: var(--bs-danger); }
        .seo-score__ring span { position: relative; font-size: 1.55rem; font-weight: 800; line-height: 1; color: var(--bs-emphasis-color); }
        .seo-score__ring small { font-size: .78rem; font-weight: 700; }
        .seo-score__text { min-width: 0; }
        .seo-score__title { font-size: 1.02rem; font-weight: 800; color: var(--bs-emphasis-color); }
        .seo-score__gain { margin: .25rem 0 .2rem; font-size: .88rem; color: var(--seo-text); }
        .seo-score__gain strong { color: var(--bs-success-text-emphasis); }
        .seo-score__source { margin: 0 0 .35rem; font-size: .76rem; color: var(--seo-muted); }
        .seo-score__link { display: inline-flex; align-items: center; gap: .25rem; font-size: .86rem; font-weight: 700; color: #1d4ed8; text-decoration: none; }
        .seo-score__link:hover { text-decoration: underline; }
        .seo-card--stat { display: flex; flex-direction: column; min-width: 0; }
        .seo-card__top { display: flex; gap: .55rem; align-items: center; }
        .seo-card__icon { display: inline-flex; flex: 0 0 2.2rem; width: 2.2rem; height: 2.2rem; align-items: center; justify-content: center; border-radius: 50%; font-size: 1.1rem; }
        .seo-card__icon--blue { color: #1d4ed8; background: #e0eaff; }
        .seo-card__icon--google { color: #ea4335; background: #fdecea; }
        .seo-card__icon--green { color: #15803d; background: #dcfce7; }
        .seo-card__icon--indigo { color: #4338ca; background: #e7e5ff; }
        .seo-card__label { font-size: .8rem; font-weight: 600; line-height: 1.3; color: var(--seo-muted); }
        .seo-card__value { margin-top: .45rem; font-size: 1.45rem; font-weight: 800; line-height: 1.15; color: var(--bs-emphasis-color); }
        .seo-card__trend { display: flex; flex-wrap: wrap; align-items: center; gap: .1rem .3rem; margin-top: .2rem; font-size: .78rem; font-weight: 700; }
        .seo-card__trend.is-up { color: #15803d; }
        .seo-card__trend.is-down { color: #dc2626; }
        .seo-card__trend span { flex-basis: 100%; font-weight: 500; color: var(--seo-muted); }
        .seo-spark { display: block; width: 100%; height: 34px; margin-top: auto; padding-top: .45rem; overflow: visible; }

        /* Call to act */
        .seo-banner { display: flex; align-items: center; gap: .85rem; margin: .9rem 0 1rem; padding: .8rem 1rem; border: 1px solid #c7d7fe; border-radius: 1rem; background: #eef4ff; }
        .seo-banner__icon { display: inline-flex; flex: 0 0 2rem; width: 2rem; height: 2rem; align-items: center; justify-content: center; border-radius: 50%; color: #1d4ed8; background: #dbe6ff; }
        .seo-banner p { flex: 1; margin: 0; font-size: .9rem; color: var(--seo-text); }
        .seo-banner p strong { color: #1d4ed8; }
        .seo-banner__button { --bs-btn-color: #fff; --bs-btn-bg: #1d4ed8; --bs-btn-border-color: #1d4ed8; --bs-btn-hover-color: #fff; --bs-btn-hover-bg: #1e40af; --bs-btn-hover-border-color: #1e40af; --bs-btn-active-bg: #1e3a8a; flex: 0 0 auto; white-space: nowrap; }
        [data-bs-theme="dark"] .seo-card__icon--blue { color: #a9c8f5; background: rgba(29, 78, 216, .25); }
        [data-bs-theme="dark"] .seo-card__icon--google { color: #ffb4ab; background: rgba(234, 67, 53, .2); }
        [data-bs-theme="dark"] .seo-card__icon--green { color: #86efac; background: rgba(21, 128, 61, .25); }
        [data-bs-theme="dark"] .seo-card__icon--indigo { color: #c7c2ff; background: rgba(67, 56, 202, .3); }
        [data-bs-theme="dark"] .seo-card__trend.is-up { color: #86efac; }
        [data-bs-theme="dark"] .seo-card__trend.is-down { color: #fca5a5; }
        [data-bs-theme="dark"] .seo-score__link, [data-bs-theme="dark"] .seo-banner p strong { color: #a9c8f5; }
        [data-bs-theme="dark"] .seo-banner { border-color: #33466a; background: #1b2a44; }
        [data-bs-theme="dark"] .seo-banner__icon { color: #a9c8f5; background: rgba(29, 78, 216, .3); }

        /* Filters */
        .seo-filters { display: flex; flex-wrap: wrap; gap: .45rem; margin-bottom: .85rem; }
        .seo-filter { display: inline-flex; align-items: center; gap: .4rem; padding: .35rem .8rem; border: 1px solid var(--bs-border-color); border-radius: 999px; font-size: .84rem; font-weight: 600; color: var(--seo-text); background: var(--bs-body-bg); }
        .seo-filter span { padding: 0 .4rem; border-radius: 999px; font-size: .74rem; background: var(--bs-tertiary-bg); }
        .seo-filter:hover { border-color: var(--seo-ai-text); }
        .seo-filter.is-active { border-color: var(--seo-ai-text); color: var(--seo-ai-text); background: var(--seo-ai-bg); }

        /* Recommendation rows */
        .seo-recs { overflow: hidden; border: 1px solid var(--bs-border-color); border-radius: 1rem; background: var(--bs-body-bg); }
        .seo-rec + .seo-rec { border-top: 1px solid var(--bs-border-color); }
        .seo-rec.is-marked { opacity: .5; }
        .seo-rec--snippet { --seo-accent: var(--seo-low_ctr); }
        .seo-rec--content { --seo-accent: var(--seo-striking_distance); }
        .seo-rec--basics { --seo-accent: var(--seo-on_page); }
        .seo-rec--decide { --seo-accent: var(--seo-declining); }
        .seo-rec__row { display: grid; grid-template-columns: auto minmax(0, 1fr) 9.5rem auto; gap: 1rem; align-items: center; padding: 1rem 1.2rem; }
        .seo-rec__icon { display: inline-flex; width: 2.5rem; height: 2.5rem; align-items: center; justify-content: center; border-radius: 50%; font-size: 1.2rem; color: var(--seo-accent); background: color-mix(in srgb, var(--seo-accent) 13%, transparent); }
        .seo-rec__labels { display: flex; flex-wrap: wrap; gap: .4rem; align-items: center; margin-bottom: .1rem; }
        .seo-rec__type { font-size: .74rem; font-weight: 800; color: var(--seo-accent); }
        .seo-rec__urgent { padding: 0 .45rem; border-radius: 999px; font-size: .7rem; font-weight: 800; color: var(--bs-danger-text-emphasis); background: var(--bs-danger-bg-subtle); }
        .seo-rec__title { margin: 0; font-size: 1rem; font-weight: 800; color: var(--bs-emphasis-color); }
        .seo-rec__page { margin-top: .1rem; overflow: hidden; font-size: .86rem; text-overflow: ellipsis; white-space: nowrap; }
        .seo-rec__page a { color: var(--bs-link-color); }
        .seo-rec__evidence { margin-top: .2rem; font-size: .85rem; color: var(--seo-muted); }
        .seo-rec__impact { text-align: right; }
        .seo-rec__impact strong { display: block; font-size: 1.35rem; font-weight: 800; line-height: 1.1; color: var(--bs-success-text-emphasis); }
        .seo-rec__impact span { font-size: .78rem; color: var(--seo-muted); }
        .seo-rec__impact .seo-rec__impact-text { display: block; font-size: .78rem; line-height: 1.3; }
        .seo-rec__buttons { display: flex; gap: .45rem; justify-content: flex-end; white-space: nowrap; }
        .seo-rec__buttons [data-seo-view] .when-open, .seo-rec__buttons [data-seo-view][aria-expanded="true"] .when-closed { display: none; }
        .seo-rec__buttons [data-seo-view][aria-expanded="true"] .when-open { display: inline; }
        .seo-rec__details { padding: 0 1.2rem 1.1rem 4.7rem; }

        /* Tabs */
        .seo-tabs { gap: .25rem; margin-bottom: 1rem; border-bottom: 1px solid var(--bs-border-color); }
        .seo-tabs .nav-link { display: inline-flex; align-items: center; gap: .4rem; border: 0; border-bottom: 3px solid transparent; border-radius: 0; padding: .6rem .9rem; font-weight: 700; background: none; }
        .seo-tabs .nav-link.active { color: var(--bs-emphasis-color); border-bottom-color: var(--bs-primary); }
        .seo-tabs .badge { font-weight: 700; }

        /* Task card */
        .seo-task__issues { display: grid; gap: .3rem; margin: .5rem 0 0; padding: 0; list-style: none; font-size: .9rem; }
        .seo-task__issues i { margin-right: .4rem; color: var(--bs-danger); }
        .seo-task__label { display: flex; align-items: center; gap: .35rem; margin-bottom: .4rem; font-size: .78rem; font-weight: 800; text-transform: uppercase; letter-spacing: .03em; color: var(--seo-muted); }
        .seo-task__label--plan { color: var(--seo-ai-text); }
        .seo-task__searches { margin-top: .9rem; }
        .seo-task__chips { display: flex; flex-wrap: wrap; gap: .4rem; }
        .seo-task__chip { display: inline-flex; align-items: baseline; gap: .4rem; padding: .25rem .6rem; border-radius: 999px; font-size: .85rem; color: var(--seo-text); background: var(--bs-tertiary-bg); }
        .seo-task__chip small { font-size: .72rem; color: var(--seo-muted); }
        .seo-task__plan { margin-top: 1rem; padding: .85rem 1rem; border-left: 3px solid var(--bs-success); border-radius: .8rem; background: var(--bs-success-bg-subtle); }
        .seo-task__plan-list { display: grid; gap: .35rem; margin: 0; padding: 0; list-style: none; font-size: .9rem; color: var(--seo-text); }
        .seo-task__plan-list i { margin-right: .4rem; color: var(--bs-success); }
        .seo-task__plan-list .is-manual { color: var(--seo-muted); }
        .seo-task__plan-list .is-manual i { color: var(--seo-muted); }
        .seo-task__plan-note { margin: .55rem 0 0; font-size: .78rem; color: var(--seo-muted); }
        .seo-task__plan-note i { margin-right: .3rem; }
        .seo-task__plan--manual { display: flex; gap: .6rem; align-items: flex-start; border-left: 3px solid #e8a317; font-size: .9rem; line-height: 1.55; color: var(--seo-text); background: var(--bs-warning-bg-subtle); }
        .seo-task__why { margin-bottom: .9rem; padding: .8rem 1rem; border-left: 3px solid var(--seo-why-line); border-radius: .8rem; background: var(--seo-why-bg); }
        .seo-task__why .seo-task__label { color: var(--seo-why-line); }
        .seo-task__why p { margin: 0; font-size: .92rem; line-height: 1.55; color: var(--seo-text); }

        /* The AI's reading of a task */
        .seo-advice { margin: 1rem 0; padding: .9rem 1rem; border: 1px solid var(--seo-advice-line); border-radius: .8rem; background: var(--bs-body-bg); box-shadow: 0 .25rem .9rem rgba(16, 24, 40, .06); }
        .seo-advice__head { display: flex; align-items: center; gap: .4rem; margin-bottom: .5rem; font-size: .8rem; font-weight: 800; text-transform: uppercase; letter-spacing: .03em; color: var(--seo-advice); }
        .seo-advice__summary { margin: 0 0 .6rem; font-size: .95rem; font-weight: 600; line-height: 1.55; color: var(--seo-text); }
        .seo-advice__steps { display: grid; gap: .45rem; margin: 0; padding: 0; list-style: none; counter-reset: advice; }
        .seo-advice__steps li { position: relative; padding-left: 2rem; font-size: .9rem; line-height: 1.5; color: var(--seo-text); counter-increment: advice; }
        .seo-advice__steps li::before { position: absolute; top: .05rem; left: 0; display: inline-flex; width: 1.4rem; height: 1.4rem; align-items: center; justify-content: center; border-radius: 50%; font-size: .75rem; font-weight: 800; color: #fff; background: var(--seo-advice); content: counter(advice); }
        .seo-advice__loading { display: flex; align-items: center; gap: .5rem; font-size: .9rem; color: var(--seo-text); }
        .seo-advice__loading i { animation: seo-spin 1s linear infinite; color: var(--seo-advice); }
        .seo-advice.is-error { border-color: var(--bs-danger); background: var(--bs-danger-bg-subtle); color: var(--bs-danger-text-emphasis); }
        .seo-advice__note { margin: .6rem 0 0; font-size: .76rem; font-weight: 500; color: var(--seo-muted); }

        /* Clicking inside a tab must not draw the browser's focus box around it; keyboard focus still shows. */
        .seo-work .tab-pane:focus:not(:focus-visible) { outline: 0; }
        .seo-task__status { display: flex; flex-wrap: wrap; gap: .4rem; }
        .seo-task__plan--manual i { margin-top: .15rem; font-size: 1.15rem; color: #b26b00; }
        .seo-task__actions { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; margin-top: 1rem; }
        .seo-task__metrics { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .5rem; margin: .6rem 0 0; }
        .seo-task__metrics > div { padding: .5rem .6rem; border-radius: .6rem; background: var(--bs-tertiary-bg); }
        .seo-task__metrics dt { font-size: .74rem; font-weight: 600; line-height: 1.35; color: var(--seo-muted); }
        .seo-task__metrics dd { margin: 0; font-weight: 800; color: var(--bs-emphasis-color); }

        /* Results */
        .seo-results-summary { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .6rem; margin: 0 0 .75rem; }
        .seo-results-summary > div { padding: .8rem .9rem; border: 1px solid var(--bs-border-color); border-radius: .8rem; }
        .seo-results-summary dt { font-size: .78rem; font-weight: 600; color: var(--seo-muted); }
        .seo-results-summary dd { margin: .15rem 0 0; font-size: 1.4rem; font-weight: 800; }
        .seo-results-how { display: flex; gap: .5rem; margin-bottom: 1rem; padding: .7rem .85rem; border-radius: .7rem; font-size: .82rem; color: var(--seo-muted); background: var(--bs-tertiary-bg); }
        .seo-outcome { margin-bottom: .75rem; border: 1px solid var(--bs-border-color); border-left: 4px solid var(--bs-border-color); border-radius: .9rem; }
        .seo-outcome--success { border-left-color: var(--bs-success); }
        .seo-outcome--danger { border-left-color: var(--bs-danger); }
        .seo-outcome--waiting { border-left-color: var(--bs-primary); }
        .seo-outcome__body { padding: 1rem 1.15rem; }
        .seo-outcome__head { display: flex; gap: .75rem; align-items: flex-start; justify-content: space-between; }
        .seo-outcome__head-text { min-width: 0; }
        .seo-outcome__title { margin: 0; font-size: .98rem; font-weight: 800; color: var(--bs-emphasis-color); }
        .seo-outcome__meta { display: flex; flex-wrap: wrap; gap: .25rem .75rem; margin-top: .2rem; font-size: .78rem; color: var(--seo-muted); }
        .seo-outcome__by i { margin-right: .2rem; }
        .seo-outcome__did { margin-top: .2rem; font-size: .82rem; }
        .seo-outcome__verdict { flex: 0 0 auto; padding: .25rem .65rem; border-radius: 999px; font-size: .78rem; font-weight: 800; }
        .seo-outcome__wait { display: flex; align-items: center; gap: .75rem; margin-top: .75rem; font-size: .82rem; color: var(--seo-muted); }
        .seo-outcome__wait .progress { flex: 1; height: .45rem; }
        .seo-outcome__wait i { margin-right: .25rem; }
        .seo-outcome__line { margin: .7rem 0 .4rem; font-size: .9rem; }
        .seo-outcome__figures { display: flex; flex-wrap: wrap; gap: .5rem 1.25rem; margin: 0; font-size: .82rem; }
        .seo-outcome__figures dt { font-weight: 600; color: var(--seo-muted); }
        .seo-outcome__figures dd { margin: 0; font-weight: 800; color: var(--bs-emphasis-color); }
        .seo-outcome__figures .is-net dd { color: inherit; }
        .seo-empty { padding: 2rem 1rem; border: 1px dashed var(--bs-border-color); border-radius: 1rem; text-align: center; color: var(--seo-muted); }
        .seo-empty i { display: block; margin-bottom: .5rem; font-size: 2rem; }

        /* Setup (not connected) */
        .seo-task-setup { overflow: hidden; border: 1px solid var(--bs-border-color); border-radius: 1rem; }
        .seo-task-setup__body { display: flex; align-items: center; gap: 1.1rem; padding: 1.35rem 1.5rem; }
        .seo-task-setup__icon { display: inline-flex; width: 3rem; height: 3rem; flex: 0 0 3rem; align-items: center; justify-content: center; border-radius: .9rem; font-size: 1.35rem; color: var(--bs-primary); background: var(--bs-primary-bg-subtle); }
        .seo-task-setup__content { min-width: 0; flex: 1; }
        .seo-task-setup__eyebrow { margin-bottom: .2rem; font-size: .75rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; color: var(--seo-muted); }
        .seo-task-setup__title { margin: 0 0 .25rem; font-size: 1.05rem; font-weight: 800; color: var(--bs-emphasis-color); }
        .seo-task-setup__hint { margin: 0; font-size: .85rem; color: var(--seo-muted); }
        .seo-task-setup__action { flex: 0 0 auto; white-space: nowrap; }

        /* Fix dialog */
        .seo-fix__progress { display: grid; gap: .35rem; margin: 0 0 1rem; padding: 0; list-style: none; font-size: .88rem; }
        .seo-fix__progress li { display: flex; align-items: center; gap: .5rem; }
        .seo-fix__progress .is-running i { animation: seo-spin 1s linear infinite; }
        .seo-fix__progress .is-done i { color: var(--bs-success); }
        .seo-fix__progress .is-failed i { color: var(--bs-danger); }
        @keyframes seo-spin { to { transform: rotate(360deg); } }
        .seo-fix__block { margin-bottom: 1rem; padding: 1rem 1.1rem; border: 1px solid var(--bs-border-color); border-radius: .9rem; }
        .seo-fix__block.is-off > :not(.seo-fix__block-head) { opacity: .45; }
        .seo-fix__block-head { display: flex; gap: .75rem; align-items: flex-start; justify-content: space-between; margin-bottom: .6rem; }
        .seo-fix__block-title { margin: 0; font-size: 1rem; font-weight: 800; color: var(--bs-emphasis-color); }
        .seo-fix__block-help { margin: .15rem 0 0; font-size: .8rem; color: var(--seo-muted); }
        .seo-fix__include { flex: 0 0 auto; font-size: .82rem; font-weight: 600; }
        .seo-fix__compare { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; margin-bottom: .75rem; }
        .seo-fix__caption { margin-bottom: .3rem; font-size: .74rem; font-weight: 800; text-transform: uppercase; letter-spacing: .03em; color: var(--seo-muted); }
        .seo-serp { padding: .7rem .85rem; border: 1px solid var(--bs-border-color); border-radius: .7rem; font-family: Arial, Helvetica, sans-serif; background: var(--bs-body-bg); }
        .seo-serp--after { border-color: var(--bs-success); }
        .seo-serp__site { overflow: hidden; font-size: .78rem; text-overflow: ellipsis; white-space: nowrap; color: #4d5156; }
        .seo-serp__title { margin: .1rem 0 .15rem; font-size: 1.05rem; line-height: 1.3; color: #1a0dab; }
        .seo-serp__desc { font-size: .84rem; line-height: 1.45; color: #4d5156; }
        .seo-serp .is-empty { font-style: italic; color: var(--seo-muted); }
        [data-bs-theme="dark"] .seo-serp__site, [data-bs-theme="dark"] .seo-serp__desc { color: #bdc1c6; }
        [data-bs-theme="dark"] .seo-serp__title { color: #8ab4f8; }
        .seo-fix__options { display: grid; gap: .4rem; margin-bottom: .75rem; }
        .seo-fix__option { display: flex; gap: .55rem; align-items: flex-start; padding: .55rem .7rem; border: 1px solid var(--bs-border-color); border-radius: .65rem; cursor: pointer; font-size: .86rem; }
        .seo-fix__option:has(input:checked) { border-color: var(--bs-primary); background: var(--bs-primary-bg-subtle); }
        .seo-fix__option small { display: block; color: var(--seo-muted); }
        .seo-fix__option strong, .seo-fix__include .form-check-label, #seoFixModal .form-label { color: var(--seo-text); }
        /* The admin forces dark input text (!important) in every theme; in dark mode that is unreadable here. */
        [data-bs-theme="dark"] #seoFixModal .form-control { color: var(--seo-text) !important; }
        .seo-fix__counter { font-size: .74rem; color: var(--seo-muted); }
        .seo-fix__counter.is-over { color: var(--bs-danger); font-weight: 700; }
        .seo-fix__covers { display: flex; flex-wrap: wrap; gap: .35rem; margin-bottom: .6rem; font-size: .8rem; }
        .seo-fix__covers span { padding: .1rem .5rem; border-radius: 999px; color: var(--seo-text); background: var(--bs-tertiary-bg); }
        .seo-fix__item { display: flex; gap: .65rem; align-items: flex-start; padding: .6rem 0; border-top: 1px dashed var(--bs-border-color); }
        .seo-fix__item:first-of-type { border-top: 0; }
        .seo-fix__item > div { min-width: 0; flex: 1; }
        .seo-fix__item img { flex: 0 0 72px; width: 72px; height: 72px; border-radius: .5rem; object-fit: cover; background: var(--bs-tertiary-bg); }
        .seo-fix__error { margin: 0; font-size: .86rem; color: var(--bs-danger-text-emphasis); }
        .seo-fix__done { padding: 1.5rem .5rem; text-align: center; }
        .seo-fix__done > i { display: block; margin-bottom: .5rem; font-size: 3rem; color: var(--bs-success); }
        .seo-fix__done h3 { font-size: 1.2rem; font-weight: 800; }
        .seo-fix__note { margin: 0; font-size: .78rem; color: var(--seo-muted); }
        .seo-fix__preview { padding: .9rem 1rem; border: 1px solid var(--bs-border-color); border-left: 3px solid var(--bs-success); border-radius: .7rem; background: var(--bs-body-bg); color: var(--seo-text); }
        .seo-fix__preview h4 { margin: 0 0 .4rem; font-size: 1.05rem; font-weight: 800; color: var(--bs-emphasis-color); }
        .seo-fix__preview p { margin: 0 0 .5rem; font-size: .9rem; line-height: 1.55; }
        .seo-fix__preview ul { margin: 0; padding-left: 1.2rem; font-size: .9rem; list-style: disc; }
        .seo-fix__preview .is-question { margin-bottom: .15rem; font-weight: 700; color: var(--bs-emphasis-color); }
        .seo-fix__edit { margin-top: .6rem; }
        .seo-fix__edit summary { display: inline-flex; align-items: center; gap: .3rem; font-size: .84rem; font-weight: 600; color: var(--bs-link-color); cursor: pointer; }
        .seo-fix__edit[open] summary { margin-bottom: .6rem; }

        .seo-work a:focus-visible, .seo-work button:focus-visible, .seo-work summary:focus-visible { outline: 3px solid rgba(var(--bs-primary-rgb), .35); outline-offset: 2px; }
        @media (max-width: 1199.98px) {
            .seo-overview { grid-template-columns: repeat(4, minmax(0, 1fr)); }
            .seo-card--score { grid-column: 1 / -1; }
        }
        @media (max-width: 991.98px) {
            .seo-overview { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .seo-card--score { grid-column: 1 / -1; }
            .seo-banner { flex-wrap: wrap; }
            .seo-rec__row { grid-template-columns: auto minmax(0, 1fr) auto; }
            .seo-rec__buttons { grid-column: 2 / -1; justify-content: flex-start; }
            .seo-rec__details { padding-left: 1.2rem; }
        }
        @media (max-width: 767.98px) {
            .seo-results-summary, .seo-task__metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .seo-rec__impact { grid-column: 2; text-align: left; }
            .seo-banner { flex-wrap: wrap; }
            .seo-banner p { flex: 1 1 calc(100% - 3rem); }
            .seo-banner__button { flex: 1 1 100%; }
            .seo-fix__compare { grid-template-columns: 1fr; }
            .seo-task-setup__body { align-items: flex-start; flex-wrap: wrap; padding: 1.1rem; }
        }
    </style>
@endpush

@section('content')
    <x-admin.page-header :title="__('ai.tasks.title')">
        <x-slot:breadcrumbs>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('ai.tasks.title') }}</li>
        </x-slot:breadcrumbs>
    </x-admin.page-header>

    @if(! $connected)
        <div data-tour="seo-tasks.setup" class="card seo-task-setup">
            <div class="seo-task-setup__body">
                <span class="seo-task-setup__icon"><i class="ti ti-brand-google" aria-hidden="true"></i></span>
                <div class="seo-task-setup__content">
                    <div class="seo-task-setup__eyebrow">Google Search Console</div>
                    <h2 class="seo-task-setup__title">{{ __('ai.tasks.not_connected') }}</h2>
                    <p class="seo-task-setup__hint">{{ __('ai.tasks.connect_hint') }}</p>
                </div>
                @if(auth()->user()?->can('settings.view') && auth()->user()?->can('settings.update'))
                    <a data-tour="seo-tasks.connect" class="btn btn-primary btn-sm seo-task-setup__action" href="{{ route('admin.settings.index', ['tab' => 'analytics']) }}">
                        {{ __('ai.tasks.connect') }}<i class="ti ti-arrow-right ms-1" aria-hidden="true"></i>
                    </a>
                @endif
            </div>
        </div>
    @elseif($error)
        <div class="alert alert-warning" role="alert">{{ $error }}</div>
    @elseif($weekly['synced_through'] === null)
        <div class="alert alert-info" role="status"><i class="ti ti-clock me-1"></i>{{ __('ai.tasks.pending') }}</div>
    @else
        @php
            $tasks = $weekly['tasks'];
            $totalGain = array_sum(array_map(fn ($task) => (int) ($task['gain'] ?? 0), $tasks));
            $outcomes = $results['outcomes'] ?? [];
            $waiting = array_values(array_filter($outcomes, fn ($o) => ($o['status'] ?? null) === 'waiting'));
            $measured = array_values(array_filter($outcomes, fn ($o) => ($o['status'] ?? null) === 'measured'));
            $summary = $results['summary'] ?? [];
        @endphp
        @php
            // Optimisation score, as Google Ads shows one: the clicks the website has against the clicks it
            // could have with every recommendation done. Only with Search Console totals.
            $siteClicks = (int) ($weekly['totals']['current']['clicks'] ?? 0);
            $score = $weekly['totals'] ? ($siteClicks + $totalGain > 0 ? (int) round($siteClicks / ($siteClicks + $totalGain) * 100) : 100) : null;
            $scoreTone = $score === null ? 'secondary' : ($score >= 80 ? 'success' : ($score >= 50 ? 'warning' : 'danger'));
            $categories = ['snippet' => 0, 'content' => 0, 'basics' => 0, 'decide' => 0];
            foreach ($tasks as $task) {
                $categories[['low_ctr' => 'snippet', 'striking_distance' => 'content', 'on_page' => 'basics'][$task['type']] ?? 'decide']++;
            }
        @endphp
        <div class="seo-work">
            {{-- Overview, as ad dashboards show one: the score card, one card per figure with its trend, then the call to act. --}}
            <section class="seo-overview" data-tour="seo-tasks.stats">
                <article class="seo-card seo-card--score">
                    @if($score !== null)
                        <div class="seo-score__ring seo-score__ring--{{ $scoreTone }}" style="--score: {{ $score }}" role="img" aria-label="{{ __('ai.tasks.score.title') }}: {{ $score }}%">
                            <span>{{ $score }}<small>%</small></span>
                        </div>
                    @endif
                    <div class="seo-score__text">
                        <div class="seo-score__title">
                            {{ __('ai.tasks.score.title') }}@if($score !== null)&nbsp;@include('admin.ai.seo-tasks._help', ['text' => __('ai.tasks.score.help', ['clicks' => number_format($siteClicks), 'gain' => number_format($totalGain)])])@endif
                        </div>
                        <p class="seo-score__gain">
                            @if($totalGain > 0)
                                {!! str_replace(':gain', '<strong>'.e(number_format($totalGain)).'</strong>', e(__('ai.tasks.score.gain_short', ['count' => count($tasks)]))) !!}
                            @else
                                {{ __('ai.tasks.score.done') }}
                            @endif
                        </p>
                        <p class="seo-score__source"><i class="ti ti-brand-google" aria-hidden="true"></i> {{ __('ai.tasks.synced_through', ['date' => \Illuminate\Support\Carbon::parse($weekly['synced_through'])->format('d/m/Y')]) }}</p>
                        @if($tasks !== [])
                            <a class="seo-score__link" href="#seo-pane-todo" data-seo-goto-todo>{{ __('ai.tasks.score.view') }} <i class="ti ti-arrow-right" aria-hidden="true"></i></a>
                        @endif
                    </div>
                </article>

                @if($weekly['totals'])
                    @php
                        $now = $weekly['totals']['current'];
                        $before = $weekly['totals']['previous'];
                        $daily = (array) ($weekly['totals']['daily'] ?? []);
                        // [key, shown value, value, previous, higher is better, icon, icon colour]
                        $stats = [
                            ['clicks', number_format($now['clicks']), $now['clicks'], $before['clicks'], true, 'ti-users', 'blue'],
                            ['impressions', number_format($now['impressions']), $now['impressions'], $before['impressions'], true, 'ti-brand-google', 'google'],
                            ['ctr', number_format($now['ctr'] * 100, 1).'%', $now['ctr'], $before['ctr'], true, 'ti-pointer', 'green'],
                            ['position', $now['position'] ?? '–', $now['position'], $before['position'], false, 'ti-chart-bar', 'indigo'],
                        ];
                        // The "?" of each figure explains the figure itself, against the 28 days before.
                        $explain = [
                            'clicks' => __('ai.tasks.site_explain.clicks', ['value' => number_format($now['clicks']), 'previous' => number_format($before['clicks'])]),
                            'impressions' => __('ai.tasks.site_explain.impressions', ['value' => number_format($now['impressions']), 'previous' => number_format($before['impressions'])]),
                            'ctr' => __('ai.tasks.site_explain.ctr', ['value' => number_format($now['ctr'] * 100, 1), 'previous' => number_format($before['ctr'] * 100, 1)]),
                            'position' => $now['position'] !== null ? __('ai.tasks.site_explain.position', [
                                'value' => $now['position'], 'previous' => $before['position'] ?? '–', 'where' => \App\Support\SeoPosition::where((float) $now['position']),
                            ]) : null,
                        ];
                    @endphp
                    @foreach($stats as [$key, $shown, $value, $previous, $higherIsBetter, $statIcon, $statColor])
                        @php
                            $trend = null;
                            $good = true;
                            if ($value !== null && $previous) {
                                if ($key === 'position') {
                                    // Places gained or lost read better than a percentage of a rank.
                                    $places = round($previous - $value, 1);
                                    $good = $places >= 0;
                                    $trend = $places == 0 ? __('ai.tasks.rank_same') : __($places > 0 ? 'ai.tasks.rank_up' : 'ai.tasks.rank_down', ['count' => number_format(abs($places), 1)]);
                                } else {
                                    $change = ($value - $previous) / $previous * 100;
                                    $good = $change >= 0;
                                    $trend = ($change >= 0 ? '+' : '').number_format($change, 0).'%';
                                }
                            }
                            $series = array_map(fn ($day) => $day[$key] ?? null, $daily);
                        @endphp
                        <article class="seo-card seo-card--stat">
                            <div class="seo-card__top">
                                <span class="seo-card__icon seo-card__icon--{{ $statColor }}" aria-hidden="true"><i class="ti {{ $statIcon }}"></i></span>
                                <div class="seo-card__label">{{ __('ai.tasks.'.$key) }}@if($explain[$key])&nbsp;@include('admin.ai.seo-tasks._help', ['text' => $explain[$key]])@endif</div>
                            </div>
                            <div class="seo-card__value">{{ $shown }}</div>
                            @if($trend !== null)
                                <div class="seo-card__trend {{ $good ? 'is-up' : 'is-down' }}">
                                    <i class="ti {{ $good ? 'ti-arrow-up' : 'ti-arrow-down' }}" aria-hidden="true"></i>{{ $trend }}
                                    <span>{{ __('ai.tasks.vs_previous') }}</span>
                                </div>
                            @endif
                            {!! \App\Support\Sparkline::svg($series, $good ? '#16a34a' : '#dc2626', $key === 'position') !!}
                        </article>
                    @endforeach
                @endif
            </section>

            @if($tasks !== [])
                <div class="seo-banner">
                    <span class="seo-banner__icon" aria-hidden="true"><i class="ti ti-bolt"></i></span>
                    <p>{!! str_replace(':count', '<strong>'.count($tasks).'</strong>', e(__('ai.tasks.banner', ['count' => ':count']))) !!}</p>
                    <a class="btn seo-banner__button" href="#seo-pane-todo" data-seo-goto-todo>{{ __('ai.tasks.banner_button') }} <i class="ti ti-arrow-right ms-1" aria-hidden="true"></i></a>
                </div>
            @endif

            <ul class="nav seo-tabs" role="tablist">
                @foreach(['todo' => [count($tasks), 'ti-list-check'], 'waiting' => [count($waiting), 'ti-clock-hour-4'], 'results' => [count($measured), 'ti-chart-arrows-vertical']] as $tab => [$count, $tabIcon])
                    <li class="nav-item" role="presentation">
                        <button class="nav-link @if($loop->first) active @endif" id="seo-tab-{{ $tab }}" data-bs-toggle="tab" data-bs-target="#seo-pane-{{ $tab }}" type="button" role="tab" aria-controls="seo-pane-{{ $tab }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                            <i class="ti {{ $tabIcon }}" aria-hidden="true"></i>{{ __('ai.tasks.tabs.'.$tab) }}
                            <span class="badge rounded-pill {{ $loop->first ? 'text-bg-primary' : 'text-bg-secondary' }}">{{ $count }}</span>
                        </button>
                    </li>
                @endforeach
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="seo-pane-todo" role="tabpanel" aria-labelledby="seo-tab-todo" tabindex="0">
                    @if($tasks !== [])
                        <div class="seo-filters" role="group" aria-label="{{ __('ai.tasks.tabs.todo') }}">
                            <button type="button" class="seo-filter is-active" data-seo-filter="all" aria-pressed="true">{{ __('ai.tasks.filters.all') }} <span>{{ count($tasks) }}</span></button>
                            @foreach($categories as $category => $count)
                                @if($count > 0)
                                    <button type="button" class="seo-filter" data-seo-filter="{{ $category }}" aria-pressed="false">{{ __('ai.tasks.filters.'.$category) }} <span>{{ $count }}</span></button>
                                @endif
                            @endforeach
                        </div>
                        <div class="seo-recs">
                            @foreach($tasks as $task)
                                @include('admin.ai.seo-tasks._task', ['task' => $task])
                            @endforeach
                        </div>
                        <div class="seo-empty d-none" data-seo-no-match>{{ __('ai.tasks.no_match') }}</div>
                    @else
                        <div class="seo-empty"><i class="ti ti-circle-check text-success" aria-hidden="true"></i>{{ __('ai.tasks.empty') }}</div>
                    @endif
                    @if(($weekly['hidden'] ?? 0) > 0)
                        <p class="small seo-muted mt-3 mb-0"><i class="ti ti-eye-off me-1" aria-hidden="true"></i>{{ __('ai.tasks.hidden_note', ['count' => $weekly['hidden']]) }}</p>
                    @endif
                </div>

                <div class="tab-pane fade" id="seo-pane-waiting" role="tabpanel" aria-labelledby="seo-tab-waiting" tabindex="0">
                    @if($results === null)
                        <div class="alert alert-warning">{{ __('ai.tasks.results.not_available') }}</div>
                    @endif
                    @forelse($waiting as $outcome)
                        @include('admin.ai.seo-tasks._outcome', ['outcome' => $outcome])
                    @empty
                        <div class="seo-empty"><i class="ti ti-clock-hour-4" aria-hidden="true"></i>{{ __('ai.tasks.results.waiting_empty') }}</div>
                    @endforelse
                </div>

                <div class="tab-pane fade" id="seo-pane-results" role="tabpanel" aria-labelledby="seo-tab-results" tabindex="0">
                    @if($results === null)
                        <div class="alert alert-warning">{{ __('ai.tasks.results.not_available') }}</div>
                    @endif
                    @if($measured !== [])
                        <dl class="seo-results-summary">
                            <div><dt>{{ __('ai.tasks.results.summary_better') }}</dt><dd class="text-success">{{ $summary['better'] ?? 0 }}</dd></div>
                            <div><dt>{{ __('ai.tasks.results.summary_flat') }}</dt><dd>{{ $summary['flat'] ?? 0 }}</dd></div>
                            <div><dt>{{ __('ai.tasks.results.summary_worse') }}</dt><dd class="text-danger">{{ $summary['worse'] ?? 0 }}</dd></div>
                            <div><dt>{{ __('ai.tasks.results.summary_extra') }}</dt><dd class="{{ ($summary['extra_clicks'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">{{ ($summary['extra_clicks'] ?? 0) >= 0 ? '+' : '' }}{{ number_format($summary['extra_clicks'] ?? 0) }}</dd></div>
                        </dl>
                    @endif
                    <p class="seo-results-how"><i class="ti ti-scale" aria-hidden="true"></i><span>{{ __('ai.tasks.results.how') }}</span></p>
                    @forelse($measured as $outcome)
                        @include('admin.ai.seo-tasks._outcome', ['outcome' => $outcome])
                    @empty
                        <div class="seo-empty"><i class="ti ti-chart-arrows-vertical" aria-hidden="true"></i>{{ __('ai.tasks.results.empty') }}</div>
                    @endforelse
                </div>
            </div>
        </div>

        @include('admin.ai.seo-tasks._fix-modal')
    @endif
@endsection

@php
    $seoTasksConfig = [
        'csrf' => csrf_token(),
        'text' => [
            'undo' => __('ai.tasks.undo'),
            'adviceTitle' => __('ai.tasks.advice_title'),
            'adviceLoading' => __('ai.tasks.advice_loading'),
            'adviceNote' => __('ai.tasks.advice_note'),
            'failed' => __('ai.errors.default'),
            'fix' => __('ai.tasks.fix'),
            'imageCount' => \App\Services\AiGateway\SeoTaskFixer::MAX_IMAGES,
        ],
    ];
@endphp
@push('scripts')
    <script type="application/json" id="seoTasksConfig">@json($seoTasksConfig)</script>
    <script src="{{ \App\Support\AssetVersion::url('admin-assets/js/seo-tasks.js') }}" defer></script>
@endpush
