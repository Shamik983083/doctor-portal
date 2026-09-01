@extends('layouts.clinician-exact')

@section('title', 'Refills')
@section('page-title', 'Refills')

{{--
    Refills (Devin msg 2285): the same review grid as Case Queue and My Cases,
    filtered to check-ins from patients this clinician has seen before. Renders
    the shared _review-grid partial so it looks and behaves identically.
--}}

@section('view')
<div class="page-head">
    <div class="eyebrow">Tasks</div>
    <h1>Refills</h1>
    <p>Check-ins from patients you have seen before. Same grid as the queue, filtered to returning patients.</p>
</div>

@include('clinician.cases._review-grid', [
    'cases'   => $cases,
    'eyebrow' => 'Refills',
    'title'   => 'Returning patients, continuity of care',
    'sub'     => 'Check-ins routed back to you because you treated these patients on their last visit.',
])
@endsection
