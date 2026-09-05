<?php

namespace App\Services;

use Com\Tecnick\Barcode\Barcode;

final class QrCodeRenderer
{
    public function png(string $url): string
    {
        return (new Barcode)->getBarcodeObj(
            type: 'QRCODE,H',
            code: $url,
            width: -4,
            height: -4,
            color: 'black',
            padding: [4, 4, 4, 4],
        )->getPngData(false);
    }
}
