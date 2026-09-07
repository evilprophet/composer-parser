<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Model;

use EvilStudio\ComposerParser\Api\Data\StylingConfigInterface;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class StylingConfig implements StylingConfigInterface
{
    protected const string HEX_COLOR_PREFIX = '#';

    protected string $groupHeaderBackgroundColor;
    protected array $cellStyleMapping;
    protected array $securityHighlightStyle;

    public function __construct(array $stylingConfigData)
    {
        $this->groupHeaderBackgroundColor = self::normalizeHexColor((string) ($stylingConfigData['groupHeaderBackgroundColor'] ?? ''));
        $this->securityHighlightStyle = self::buildSecurityHighlightStyle(
            (array) ($stylingConfigData['securityHighlight'] ?? [])
        );
        $this->cellStyleMapping = array_map(
            static function (mixed $cellStyle): mixed {
                if (!is_array($cellStyle)) {
                    return $cellStyle;
                }

                foreach (['color', 'backgroundColor'] as $field) {
                    if (isset($cellStyle[$field]) && is_string($cellStyle[$field])) {
                        $cellStyle[$field] = self::normalizeHexColor($cellStyle[$field]);
                    }
                }

                return $cellStyle;
            },
            (array) ($stylingConfigData['cellStyleMapping'] ?? [])
        );
    }

    public function getGroupHeaderBackgroundColor(): string
    {
        return $this->groupHeaderBackgroundColor;
    }

    public function getCellStyleMapping(): array
    {
        return $this->cellStyleMapping;
    }

    public function getSecurityHighlightStyle(): array
    {
        return $this->securityHighlightStyle;
    }

    protected static function buildSecurityHighlightStyle(array $securityHighlight): array
    {
        $style = [];

        $color = (string) ($securityHighlight['color'] ?? '');
        if ($color !== '') {
            $style['font']['color']['rgb'] = self::normalizeHexColor($color);
        }

        $backgroundColor = (string) ($securityHighlight['backgroundColor'] ?? '');
        if ($backgroundColor !== '') {
            $style['fill']['fillType'] = Fill::FILL_SOLID;
            $style['fill']['startColor']['rgb'] = self::normalizeHexColor($backgroundColor);
        }

        return $style;
    }

    protected static function normalizeHexColor(string $color): string
    {
        if (!str_starts_with($color, self::HEX_COLOR_PREFIX)) {
            return $color;
        }

        return substr($color, strlen(self::HEX_COLOR_PREFIX));
    }
}
