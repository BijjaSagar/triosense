<?php

declare(strict_types=1);

use App\Domain\Fifo\CutoffCalculator;
use App\Domain\Fifo\LiveState;
use App\Domain\Fifo\Mode;
use App\Domain\Fifo\Status;
use Carbon\CarbonImmutable;

/**
 * Tests for the FIFO state machine. These are pure unit tests — no DB, no Redis.
 * Coverage target for App\Domain\Fifo is ≥95%. See BUILD_SPEC Sprint 3.
 */

function makeState(
    int $quota = 5000,
    int $issued = 0,
    int $queueHead = 0,
    int $queueTail = 0,
    float $issuanceRate = 18.0,
    float $arrivalRate = 20.0,
    Mode $mode = Mode::LIVE,
): LiveState {
    return new LiveState(
        locationId: 3,
        quota: $quota,
        issued: $issued,
        queueHead: $queueHead,
        queueTail: $queueTail,
        issuanceRatePerMin: $issuanceRate,
        arrivalRatePerMin: $arrivalRate,
        now: CarbonImmutable::parse('2026-06-20 06:00:00', 'Asia/Kolkata'),
        closesAt: CarbonImmutable::parse('2026-06-20 12:00:00', 'Asia/Kolkata'),
        mode: $mode,
    );
}

it('is OPEN at the start of the day', function () {
    $decision = (new CutoffCalculator())->decide(makeState(
        issued: 0, queueHead: 0, queueTail: 50,
    ));

    expect($decision->status)->toBe(Status::OPEN);
    expect($decision->cutoffPosition)->toBeNull();
});

it('declares CUTOFF when queue already exceeds remaining tokens', function () {
    // 1,160 tokens left but 1,369 people in queue → cutoff at #5000
    $decision = (new CutoffCalculator())->decide(makeState(
        quota: 5000, issued: 3840,
        queueHead: 3841, queueTail: 5210,
    ));

    expect($decision->status)->toBe(Status::CUTOFF_DECLARED);
    expect($decision->cutoffPosition)->toBe(5000);
    expect($decision->reason)->toBe('queue_exceeds_remaining');
});

it('declares APPROACHING when forecast exceeds remaining', function () {
    // 2 hours of operating time left, arriving 30/min → 3600 expected new
    // 1000 currently queued, 2000 tokens left → forecast overshoots
    $decision = (new CutoffCalculator())->decide(makeState(
        quota: 5000, issued: 3000,
        queueHead: 3000, queueTail: 4000,      // 1000 queued, 2000 remaining
        issuanceRate: 20.0, arrivalRate: 30.0,
    ));

    expect($decision->status)->toBe(Status::APPROACHING_CUTOFF);
    expect($decision->cutoffPosition)->toBeNull();
});

it('is CLOSED when quota exhausted', function () {
    $decision = (new CutoffCalculator())->decide(makeState(
        quota: 5000, issued: 5000,
        queueHead: 5000, queueTail: 5200,
    ));

    expect($decision->status)->toBe(Status::CLOSED);
    expect($decision->cutoffPosition)->toBe(5000);
});

it('does not forecast when issuance rate is below minimum threshold', function () {
    // Issuance rate too low to forecast reliably — should not declare APPROACHING
    // even though arrival rate is high.
    $decision = (new CutoffCalculator())->decide(makeState(
        quota: 5000, issued: 3000,
        queueHead: 3000, queueTail: 4000,
        issuanceRate: 0.5, arrivalRate: 30.0,
    ));

    expect($decision->status)->toBe(Status::OPEN);
});

it('treats DISABLED mode as a no-op OPEN', function () {
    $decision = (new CutoffCalculator())->decide(makeState(
        quota: 5000, issued: 4999,
        queueHead: 4999, queueTail: 6000,       // would normally be CUTOFF
        mode: Mode::DISABLED,
    ));

    expect($decision->status)->toBe(Status::OPEN);
    expect($decision->reason)->toBe('mode_disabled');
});

it('handles malformed state where head > tail gracefully', function () {
    // Should not throw; queueLength() clamps to 0.
    $decision = (new CutoffCalculator())->decide(makeState(
        quota: 5000, issued: 1000,
        queueHead: 100, queueTail: 50,
    ));

    expect($decision->status)->toBe(Status::OPEN);
});

it('handles zero quota', function () {
    $decision = (new CutoffCalculator())->decide(makeState(
        quota: 0, issued: 0,
        queueHead: 0, queueTail: 100,
    ));

    expect($decision->status)->toBe(Status::CLOSED);
});
