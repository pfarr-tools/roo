@extends('public.layouts.teaching-unit')

@section('content')
<main class="public-unit container">
    <header class="public-unit__header">
        <p class="public-unit__context">{{ $view->school->name }} · {{ $view->group->name }}</p>
        <h1>{{ $view->unit->title }}</h1>
        @if ($view->creator)
            <p class="public-unit__creator">Erstellt von {{ $view->creator->name }}</p>
        @endif
    </header>

    @if ($view->unit->introduction_text)
        <section>
            <div class="public-unit__text">{{ $view->unit->introduction_text }}</div>
        </section>
    @endif

    <section aria-labelledby="competencies-heading">
        <h2 id="competencies-heading">Kompetenzen</h2>
        @if ($view->competencies->isNotEmpty())
            <ul>
                @foreach ($view->competencies as $competency)
                    <li>{{ $competency->local_wording ?: $competency->educationPlanCompetency?->text ?: $competency->educationPlanCompetency?->variants?->sortBy('position')->first()?->text ?: $competency->curriculumEducationPlanReference?->educationPlanCompetency?->text ?: $competency->curriculumEducationPlanReference?->educationPlanCompetency?->variants?->sortBy('position')->first()?->text ?: 'Kompetenz' }}</li>
                @endforeach
            </ul>
        @else
            <p>Für diese Einheit sind noch keine Kompetenzen hinterlegt.</p>
        @endif
    </section>

    <section aria-labelledby="lessons-heading">
        <h2 id="lessons-heading">Termine und Themen</h2>
        @if ($view->scheduledLessons->isNotEmpty())
            <ol>
                @foreach ($view->scheduledLessons as $scheduledLesson)
                    <li><time datetime="{{ $scheduledLesson['starts_at']->toIso8601String() }}">{{ $scheduledLesson['starts_at']->format('d.m.Y, H:i') }} Uhr</time> – {{ $scheduledLesson['lesson']->title }}</li>
                @endforeach
            </ol>
        @else
            <p>Es sind noch keine konkreten Termine geplant.</p>
        @endif
    </section>

    @if ($view->visiblePhaseResources->isNotEmpty() || $view->visiblePhaseLinks->isNotEmpty())
        <section aria-labelledby="materials-heading">
            <h2 id="materials-heading">Materialien</h2>
            <ul>
                @foreach ($view->visiblePhaseResources as $resource)
                    <li><a href="{{ URL::signedRoute('public.teaching-units.resources.download', ['teachingUnit' => $view->unit, 'resource' => $resource]) }}">{{ $resource->original_name }}</a></li>
                @endforeach
                @foreach ($view->visiblePhaseLinks as $link)
                    <li><a href="{{ $link->url }}" target="_blank" rel="noreferrer">{{ $link->title }}</a></li>
                @endforeach
            </ul>
        </section>
    @endif

    @php($galleryIndex = 0)
    @foreach ($view->galleries as $gallery)
        <section aria-labelledby="gallery-heading-{{ $loop->index }}" class="public-gallery-section">
            <h2 id="gallery-heading-{{ $loop->index }}">Bilder vom {{ \Carbon\Carbon::parse($gallery['date'])->format('d.m.Y') }}</h2>
            <div class="public-gallery-grid">
                @foreach ($gallery['images'] as $image)
                    <button class="public-gallery-thumbnail" type="button" data-gallery-index="{{ $galleryIndex++ }}" aria-label="Bild vergrößern: {{ $image['name'] }}">
                        <img src="{{ $image['url'] }}" alt="{{ $image['name'] }}" loading="lazy">
                    </button>
                @endforeach
            </div>
        </section>
    @endforeach

    @if ($view->contacts->isNotEmpty())
        <section aria-labelledby="contacts-heading">
            <h2 id="contacts-heading">Kontaktmöglichkeiten</h2>
            <ul>
                @foreach ($view->contacts as $contact)
                    <li>{{ $contact['label'] }}: {{ $contact['value'] }}</li>
                @endforeach
            </ul>
        </section>
    @endif
</main>

<div id="public-gallery-modal" class="public-gallery-modal" role="dialog" aria-modal="true" aria-label="Bildgalerie" hidden>
    <button class="public-gallery-modal__close" type="button" aria-label="Schließen">&times;</button>
    <button class="public-gallery-modal__previous" type="button" aria-label="Vorheriges Bild">&lsaquo;</button>
    <img class="public-gallery-modal__image" alt="">
    <button class="public-gallery-modal__next" type="button" aria-label="Nächstes Bild">&rsaquo;</button>
</div>

<script>
    (() => {
        const thumbnails = [...document.querySelectorAll('.public-gallery-thumbnail')]
        const modal = document.getElementById('public-gallery-modal')
        const image = modal?.querySelector('.public-gallery-modal__image')
        let current = 0

        function show(index) {
            if (!image || !thumbnails.length) return
            current = (index + thumbnails.length) % thumbnails.length
            const thumbnail = thumbnails[current]
            const source = thumbnail.querySelector('img')
            image.src = source.src
            image.alt = source.alt
            modal.hidden = false
            modal.querySelector('.public-gallery-modal__close').focus()
        }

        function close() {
            if (modal) modal.hidden = true
        }

        thumbnails.forEach(thumbnail => thumbnail.addEventListener('click', () => show(Number(thumbnail.dataset.galleryIndex))))
        modal?.querySelector('.public-gallery-modal__close').addEventListener('click', close)
        modal?.querySelector('.public-gallery-modal__previous').addEventListener('click', () => show(current - 1))
        modal?.querySelector('.public-gallery-modal__next').addEventListener('click', () => show(current + 1))
        modal?.addEventListener('click', event => { if (event.target === modal) close() })
        document.addEventListener('keydown', event => {
            if (!modal || modal.hidden) return
            if (event.key === 'Escape') close()
            if (event.key === 'ArrowLeft') show(current - 1)
            if (event.key === 'ArrowRight') show(current + 1)
        })
    })()
</script>
@endsection
