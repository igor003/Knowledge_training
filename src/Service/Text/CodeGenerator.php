<?php

namespace App\Service\Text;

final class CodeGenerator
{
    /**
     * @var array<string, string>
     */
    private const CYRILLIC_MAP = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e', 'ж' => 'zh',
        'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o',
        'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts',
        'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu',
        'я' => 'ya',
        'А' => 'a', 'Б' => 'b', 'В' => 'v', 'Г' => 'g', 'Д' => 'd', 'Е' => 'e', 'Ё' => 'e', 'Ж' => 'zh',
        'З' => 'z', 'И' => 'i', 'Й' => 'y', 'К' => 'k', 'Л' => 'l', 'М' => 'm', 'Н' => 'n', 'О' => 'o',
        'П' => 'p', 'Р' => 'r', 'С' => 's', 'Т' => 't', 'У' => 'u', 'Ф' => 'f', 'Х' => 'h', 'Ц' => 'ts',
        'Ч' => 'ch', 'Ш' => 'sh', 'Щ' => 'sch', 'Ъ' => '', 'Ы' => 'y', 'Ь' => '', 'Э' => 'e', 'Ю' => 'yu',
        'Я' => 'ya',
    ];

    public function baseFromName(string $name, string $fallback, int $maxLength): string
    {
        $source = strtr(trim($name), self::CYRILLIC_MAP);
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $source);
        $ascii = is_string($ascii) ? $ascii : $source;
        $code = strtolower($ascii);
        $code = preg_replace('/[^a-z0-9]+/', '_', $code) ?? '';
        $code = trim($code, '_');
        $fallback = $this->normalizeFallback($fallback);

        if ($code === '') {
            $code = $fallback;
        }

        if (!preg_match('/^[a-z]/', $code)) {
            $code = $fallback . '_' . $code;
        }

        return substr($code, 0, max(1, $maxLength));
    }

    public function candidate(string $base, int $maxLength, int $index): string
    {
        if ($index <= 1) {
            return substr($base, 0, max(1, $maxLength));
        }

        $suffix = '_' . $index;

        return substr($base, 0, max(1, $maxLength - strlen($suffix))) . $suffix;
    }

    private function normalizeFallback(string $fallback): string
    {
        $fallback = strtolower(trim($fallback));
        $fallback = preg_replace('/[^a-z0-9]+/', '_', $fallback) ?? '';
        $fallback = trim($fallback, '_');

        if ($fallback === '' || !preg_match('/^[a-z]/', $fallback)) {
            return 'item';
        }

        return $fallback;
    }
}
