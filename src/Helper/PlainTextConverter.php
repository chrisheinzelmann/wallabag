<?php

namespace Wallabag\Helper;

use Psr\Log\LoggerInterface;

/**
 * Converts plain text content to HTML using pandoc.
 */
class PlainTextConverter
{
    public function __construct(
        private readonly string $pandocBinary,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Convert plain text to HTML using pandoc.
     *
     * Strips any existing HTML tags before passing the text to pandoc,
     * so this is safe to call with content that was already partially
     * processed (e.g. wrapped in <pre> tags by graby).
     *
     * @return string|null HTML content, or null if conversion fails or text is empty
     */
    public function convert(string $text): ?string
    {
        $processClass = 'Symfony\\Component\\Process\\Process';

        $plainText = $this->normalizeUtf8(strip_tags($text));

        if ('' === trim($plainText)) {
            return null;
        }

        if (class_exists($processClass)) {
            $process = new $processClass([$this->pandocBinary, '-f', 'markdown', '-t', 'html']);
            $process->setInput($plainText);

            try {
                $process->mustRun();

                return $process->getOutput();
            } catch (\Throwable $e) {
                $this->logger->warning('PlainTextConverter: unexpected error during pandoc conversion.', [
                    'pandoc_binary' => $this->pandocBinary,
                    'error' => $e->getMessage(),
                    'stderr' => $process->getErrorOutput(),
                    'stdout' => $process->getOutput(),
                    'exit_code' => $process->getExitCode(),
                ]);

                return null;
            }
        }

        $this->logger->warning('PlainTextConverter: Symfony Process component is unavailable, using proc_open fallback.');

        return $this->convertWithProcOpen($plainText);
    }

    private function normalizeUtf8(string $text): string
    {
        if (1 === preg_match('//u', $text)) {
            return $text;
        }

        if (\function_exists('iconv')) {
            $converted = iconv('UTF-8', 'UTF-8//IGNORE', $text);
            if (false !== $converted) {
                return $converted;
            }
        }

        if (\function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($text, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
        }

        return $text;
    }

    private function convertWithProcOpen(string $plainText): ?string
    {
        $command = escapeshellarg($this->pandocBinary) . ' -f markdown -t html';
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($command, $descriptors, $pipes);

        if (!\is_resource($process)) {
            $this->logger->warning('PlainTextConverter: unable to start pandoc process with proc_open.', [
                'pandoc_binary' => $this->pandocBinary,
            ]);

            return null;
        }

        fwrite($pipes[0], $plainText);
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if (0 !== $exitCode) {
            $this->logger->warning('PlainTextConverter: pandoc proc_open fallback failed.', [
                'pandoc_binary' => $this->pandocBinary,
                'stderr' => $stderr,
                'stdout' => $stdout,
                'exit_code' => $exitCode,
            ]);

            return null;
        }

        return false === $stdout ? null : $stdout;
    }
}
