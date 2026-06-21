<?php

declare(strict_types=1);

use App\Services\Notifications\FcmService;

it('returns false when fcm server key is not configured', function () {
    config(['triosense.fcm.server_key' => null, 'triosense.fcm.credentials_path' => null]);

    $result = app(FcmService::class)->send('test-token', 'Title', 'Body');

    expect($result)->toBeFalse();
});
