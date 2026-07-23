@extends('layouts.clinician-exact')

@section('title', 'Case Queue')
@section('page-title', 'Case Queue')

{{--
    Case Queue. The grid + quick review are the shared _review-grid partial, so
    this and My Cases are literally the same surface (Devin msg 2283).
--}}

@section('view')
<div class="page-head">
    <div class="eyebrow">Clinician</div>
    <h1>Case Queue</h1>
    <p>Unclaimed cases you are licensed to review. Claim one to start.</p>
</div>

@include('clinician.cases._review-grid', [
    'cases'   => $cases,
    'eyebrow' => 'Provider review queue',
    'title'   => 'Fast review, full context one click away',
    'sub'     => 'Highest-attention cases surface first. Triage is a review-priority signal, not a clinical decision.',
])
@endsection
