<?php

declare(strict_types=1);

use App\Domain\Fifo\LiveState;
use App\Domain\Fifo\Mode;
use Carbon\CarbonImmutable;

it('computes tokens remaining and queue length', function () {
    $state = new LiveState(
        locationId: 1,
        quota: 5000,
        issued: 1200,
        queueHead: 1200,
        queueTail: 1450,
        issuanceRatePerMin: 10.0,
        arrivalRatePerMin: 12.0,
        now: CarbonImmutable::parse('2026-06-20 08:00:00', 'Asia/Kolkata'),
        closesAt: CarbonImmutable::parse('2026-06-20 12:00:00', 'Asia/Kolkata'),
    );

    expect($state->tokensRemaining())->toBe(3800)
        ->and($state->queueLength())->toBe(250);
});

it('clamps malformed queue head/tail to zero length', function () {
    $state = new LiveState(
        locationId: 1,
        quota: 5000,
        issued: 100,
        queueHead: 200,
        queueTail: 50,
        issuanceRatePerMin: 0.0,
        arrivalRatePerMin: 0.0,
        now: CarbonImmutable::parse('2026-06-20 08:00:00', 'Asia/Kolkata'),
        closesAt: CarbonImmutable::parse('2026-06-20 12:00:00', 'Asia/Kolkata'),
    );

    expect($state->queueLength())->toBe(0);
});

it('projects arrivals before close from rolling rate', function () {
    $state = new LiveState(
        locationId: 1,
        quota: 5000,
        issued: 0,
        queueHead: 0,
        queueTail: 0,
        issuanceRatePerMin: 20.0,
        arrivalRatePerMin: 30.0,
        now: CarbonImmutable::parse('2026-06-20 10:00:00', 'Asia/Kolkata'),
        closesAt: CarbonImmutable::parse('2026-06-20 12:00:00', 'Asia/Kolkata'),
    );

    expect($state->minutesUntilClose())->toBe(120.0)
        ->and($state->projectedArrivalsBeforeClose())->toBe(3600);
});

it('uses festival safety margin when festival mode is active', function () {
    $state = new LiveState(
        locationId: 1,
        quota: 5000,
        issued: 0,
        queueHead: 0,
        queueTail: 0,
        issuanceRatePerMin: 10.0,
        arrivalRatePerMin: 10.0,
        now: CarbonImmutable::parse('2026-06-20 08:00:00', 'Asia/Kolkata'),
        closesAt: CarbonImmutable::parse('2026-06-20 12:00:00', 'Asia/Kolkata'),
        mode: Mode::SHADOW,
        festivalMode: true,
    );

    expect($state->safetyMargin())->toBe(0.20);
});

it('declares approaching cutoff under festival margin buffer', function () {
    $decision = (new \App\Domain\Fifo\CutoffCalculator())->decide(new LiveState(
        locationId: 3,
        quota: 5000,
        issued: 3000,
        queueHead: 3000,
        queueTail: 4200,
        issuanceRatePerMin: 20.0,
        arrivalRatePerMin: 5.0,
        now: CarbonImmutable::parse('2026-06-20 10:00:00', 'Asia/Kolkata'),
        closesAt: CarbonImmutable::parse('2026-06-20 12:00:00', 'Asia/Kolkata'),
        festivalMode: true,
    ));

    expect($decision->status)->toBe(\App\Domain\Fifo\Status::APPROACHING_CUTOFF);
});
