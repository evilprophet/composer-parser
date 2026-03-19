<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Service\Report;

use EvilStudio\ComposerParser\Api\Data\ParsedDataInterface;
use InvalidArgumentException;

class ReportValidator
{
    public function validate(ParsedDataInterface $parsedData): void
    {
        $projects = $parsedData->getProjectNames();
        $projectSet = array_flip($projects);

        $groups = $parsedData->getProjectsData();

        foreach ($groups as $groupName => $packages) {
            if (!is_array($packages)) {
                throw new InvalidArgumentException(sprintf('Group "%s" must be an array.', $groupName));
            }

            foreach ($packages as $packageName => $packageRows) {
                if (!is_array($packageRows)) {
                    throw new InvalidArgumentException(sprintf('Package "%s" in group "%s" must be an array.', $packageName, $groupName));
                }

                foreach ($packageRows as $projectName => $cell) {
                    if (!isset($projectSet[$projectName])) {
                        throw new InvalidArgumentException(sprintf('Unknown project "%s" in package "%s".', $projectName, $packageName));
                    }

                    if (!is_array($cell) || !array_key_exists('value', $cell) || !array_key_exists('comment', $cell)) {
                        throw new InvalidArgumentException(sprintf('Cell for project "%s" in package "%s" must contain "value" and "comment".', $projectName, $packageName));
                    }
                }
            }
        }
    }
}
