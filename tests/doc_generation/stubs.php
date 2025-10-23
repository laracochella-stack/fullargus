<?php

declare(strict_types=1);

namespace PhpOffice\PhpWord {
    if (!class_exists('PhpOffice\\PhpWord\\TemplateProcessor', false)) {
        class TemplateProcessor
        {
            /** @var string */
            private $template;
            /** @var array<string,string> */
            private $values = [];

            public function __construct(string $templatePath)
            {
                if (!is_file($templatePath)) {
                    throw new \RuntimeException('Template not found: ' . $templatePath);
                }

                $contents = file_get_contents($templatePath);
                if ($contents === false) {
                    throw new \RuntimeException('Unable to read template: ' . $templatePath);
                }

                $this->template = $contents;
            }

            public function setValue(string $key, $value): void
            {
                $this->values[$key] = (string)$value;
            }

            public function saveAs(string $targetPath): void
            {
                $result = $this->template;
                foreach ($this->values as $key => $value) {
                    $result = str_replace('${' . $key . '}', $value, $result);
                }

                $bytes = file_put_contents($targetPath, $result);
                if ($bytes === false) {
                    throw new \RuntimeException('Unable to save generated file: ' . $targetPath);
                }
            }
        }
    }

    if (!class_exists('PhpOffice\\PhpWord\\Settings', false)) {
        class Settings
        {
            public static function setTempDir(string $dir): void
            {
                // noop for tests
            }
        }
    }
}

namespace {
    // Return to global namespace for requiring file without side effects.
}
