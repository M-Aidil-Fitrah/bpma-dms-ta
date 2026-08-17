<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Data\OcrResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OcrResultTest extends TestCase
{
    #[Test]
    public function hanya_hasil_ocr_yang_cukup_jelas_dianggap_layak(): void
    {
        $this->assertTrue((new OcrResult('Rekonsiliasi data penerimaan', [87.0, 84.0, 90.0]))->isLayak());
        $this->assertFalse((new OcrResult('', []))->isLayak());
        $this->assertFalse((new OcrResult('teks pendek', [10.0, 12.0]))->isLayak());
    }
}
