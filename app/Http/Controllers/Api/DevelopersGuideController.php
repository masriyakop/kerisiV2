<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DevelopersGuideController extends Controller
{
    use ApiResponse;

    /**
     * The dedicated folder for AI guideline files.
     */
    private function guidelinesDir(): string
    {
        return base_path('docs/ai-guidelines');
    }

    /**
     * The canonical guideline file.
     */
    private function guidelinePath(): string
    {
        return $this->guidelinesDir().'/CLAUDE.md';
    }

    /**
     * Root canonical file path used by AI tools auto-detection.
     */
    private function rootCanonicalPath(): string
    {
        return base_path('CLAUDE.md');
    }

    /**
     * Files that should be kept in sync with CLAUDE.md.
     */
    private function syncTargets(): array
    {
        return [
            $this->guidelinesDir().'/.cursorrules',
            $this->guidelinesDir().'/AGENTS.md',
            base_path('.cursorrules'),
            base_path('AGENTS.md'),
        ];
    }

    /**
     * Convert absolute path to project-relative display path.
     */
    private function relativePath(string $path): string
    {
        $base = base_path().DIRECTORY_SEPARATOR;

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }

    /**
     * Build mirror file content with canonical reference header.
     */
    private function mirrorContent(string $content): string
    {
        $syncHeader = "> **Canonical source: `CLAUDE.md` at project root.**\n> If this file and CLAUDE.md diverge, CLAUDE.md wins. Keep them in sync.";
        $originalHeader = "> **This file is the single source of truth for AI-assisted development.**\n> All generated code MUST conform to the patterns below. No exceptions.";

        $syncContent = str_replace($originalHeader, $syncHeader, $content);
        if ($syncContent !== $content) {
            return $syncContent;
        }

        $firstLine = strtok($content, "\n");
        if ($firstLine === false) {
            return $syncHeader."\n\n".$content;
        }

        $offset = strlen($firstLine) + 1;
        $rest = strlen($content) > $offset ? substr($content, $offset) : '';

        return $firstLine."\n\n".$syncHeader.($rest !== '' ? "\n".$rest : '');
    }

    /**
     * Build display metadata for sync status panel.
     */
    private function buildSyncStatus(string $content): array
    {
        $mirror = $this->mirrorContent($content);
        $syncFiles = [
            [
                'filename' => basename($this->rootCanonicalPath()),
                'path' => $this->relativePath($this->rootCanonicalPath()),
                'exists' => file_exists($this->rootCanonicalPath()),
                'in_sync' => file_exists($this->rootCanonicalPath())
                    && file_get_contents($this->rootCanonicalPath()) === $content,
                'read_only' => true,
                'role' => 'canonical',
            ],
        ];

        foreach ($this->syncTargets() as $target) {
            $exists = file_exists($target);
            $current = $exists ? file_get_contents($target) : null;
            $syncFiles[] = [
                'filename' => basename($target),
                'path' => $this->relativePath($target),
                'exists' => $exists,
                'in_sync' => $exists && $current === $mirror,
                'read_only' => false,
                'role' => 'mirror',
            ];
        }

        return $syncFiles;
    }

    /**
     * Get the current guideline content.
     */
    public function show(): JsonResponse
    {
        $path = $this->guidelinePath();

        if (! file_exists($path)) {
            return $this->sendError(404, 'NOT_FOUND', 'CLAUDE.md not found in docs/ai-guidelines');
        }

        $content = file_get_contents($path);

        return $this->sendOk([
            'content' => $content,
            'sync_files' => $this->buildSyncStatus($content),
        ]);
    }

    /**
     * Update the guideline content and sync to mirror files.
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'content' => 'required|string|min:1',
        ]);

        $content = $request->input('content');
        $path = $this->guidelinePath();

        // Write the canonical file
        file_put_contents($path, $content);
        $syncContent = $this->mirrorContent($content);

        // Sync to mirror files
        foreach ($this->syncTargets() as $target) {
            file_put_contents($target, $syncContent);
        }
        $syncResults = $this->buildSyncStatus($content);

        return $this->sendOk([
            'success' => true,
            'sync_files' => $syncResults,
        ]);
    }
}
