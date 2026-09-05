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
        <section aria-labelledby="introduction-heading">
            <h2 id="introduction-heading">Einführung</h2>
            <div class="public-unit__text">{{ $view->unit->introduction_text }}</div>
        </section>
    @endif

    <section aria-labelledby="competencies-heading">
        <h2 id="competencies-heading">Kompetenzen</h2>
        @if ($view->competencies->isNotEmpty())
            <ul>
                @foreach ($view->competencies as $competency)
                    <li>{{ $competency->local_wording ?: $competency->educationPlanCompetency?->text ?: 'Kompetenz' }}</li>
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
</main>
@endsection
