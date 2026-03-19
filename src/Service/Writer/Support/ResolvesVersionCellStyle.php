<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Service\Writer\Support;

use PhpOffice\PhpSpreadsheet\Style\Fill;

trait ResolvesVersionCellStyle
{
    protected function getPackageVersionCellStyle(string $versionCell, string $packageName): array
    {
        $styling = [];
        $cellStyleMapping = $this->stylingConfig->getCellStyleMapping();

        foreach ($cellStyleMapping as $cellStyle) {
            if (isset($cellStyle['packageNameRegex']) && !preg_match($cellStyle['packageNameRegex'], $packageName)) {
                continue;
            }

            if (!preg_match($cellStyle['versionRegex'], $versionCell)) {
                continue;
            }

            if (isset($cellStyle['color'])) {
                $styling['font']['color']['rgb'] = $cellStyle['color'];
            }

            if (isset($cellStyle['backgroundColor'])) {
                $styling['fill']['fillType'] = Fill::FILL_SOLID;
                $styling['fill']['startColor']['rgb'] = $cellStyle['backgroundColor'];
            }
        }

        return $styling;
    }
}
