<?php

declare(strict_types=1);

/**
 * Sync and verify AI guideline files across root/docs mirrors.
 *
 * Usage:
 *   php scripts/ai-guidelines-sync.php         # sync all targets
 *   php scripts/ai-guidelines-sync.php --check # verify all targets are in sync
 */
$root = dirname(__DIR__);
$canonicalPath = $root.'/docs/ai-guidelines/CLAUDE.md';

if (! file_exists($canonicalPath)) {
    fwrite(STDERR, "Missing canonical file: docs/ai-guidelines/CLAUDE.md\n");
    exit(1);
}

$canonical = file_get_contents($canonicalPath);
if ($canonical === false) {
    fwrite(STDERR, "Unable to read canonical file: docs/ai-guidelines/CLAUDE.md\n");
    exit(1);
}

$syncHeader = "> **Canonical source: `CLAUDE.md` at project root.**\n"
    .'> If this file and CLAUDE.md diverge, CLAUDE.md wins. Keep them in sync.';
$originalHeader = "> **This file is the single source of truth for AI-assisted development.**\n"
    .'> All generated code MUST conform to the patterns below. No exceptions.';

$mirror = str_replace($originalHeader, $syncHeader, $canonical);
if ($mirror === $canonical) {
    $firstNewline = strpos($canonical, "\n");
    if ($firstNewline === false) {
        $mirror = $syncHeader."\n\n".$canonical;
    } else {
        $firstLine = substr($canonical, 0, $firstNewline);
        $rest = substr($canonical, $firstNewline + 1);
        $mirror = $firstLine."\n\n".$syncHeader.($rest !== '' ? "\n".$rest : '');
    }
}

$targets = [
    ['path' => 'CLAUDE.md', 'expected' => $canonical],
    ['path' => 'docs/ai-guidelines/AGENTS.md', 'expected' => $mirror],
    ['path' => 'docs/ai-guidelines/.cursorrules', 'expected' => $mirror],
    ['path' => 'AGENTS.md', 'expected' => $mirror],
    ['path' => '.cursorrules', 'expected' => $mirror],
];

$checkOnly = in_array('--check', $argv, true);
$errors = [];

foreach ($targets as $target) {
    $relativePath = $target['path'];
    $absolutePath = $root.'/'.$relativePath;
    $expected = $target['expected'];

    if ($checkOnly) {
        if (! file_exists($absolutePath)) {
            $errors[] = "Missing file: {$relativePath}";

            continue;
        }

        $actual = file_get_contents($absolutePath);
        if ($actual === false) {
            $errors[] = "Unreadable file: {$relativePath}";

            continue;
        }

        if ($actual !== $expected) {
            $errors[] = "Out of sync: {$relativePath}";
        }

        continue;
    }

    $dir = dirname($absolutePath);
    if (! is_dir($dir) && ! mkdir($dir, 0777, true) && ! is_dir($dir)) {
        fwrite(STDERR, "Failed to create directory: {$dir}\n");
        exit(1);
    }

    if (file_put_contents($absolutePath, $expected) === false) {
        fwrite(STDERR, "Failed to write file: {$relativePath}\n");
        exit(1);
    }
}

if ($checkOnly) {
    if ($errors !== []) {
        fwrite(STDERR, "AI guideline sync check failed:\n");
        foreach ($errors as $error) {
            fwrite(STDERR, "- {$error}\n");
        }
        fwrite(STDERR, "Run: composer ai:sync\n");
        exit(1);
    }

    fwrite(STDOUT, "AI guideline sync check passed.\n");
    exit(0);
}

fwrite(STDOUT, "AI guideline files synced.\n");
