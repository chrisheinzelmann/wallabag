<?php

namespace Wallabag\Helper;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

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
        $plainText = strip_tags($text);

        if ('' === trim($plainText)) {
            return null;
        }

        if (!class_exists(Process::class)) {
            $this->logger->warning('PlainTextConverter: Symfony Process component is unavailable.');

            return null;
        }

        $process = new Process([$this->pandocBinary, '-f', 'markdown', '-t', 'html']);
        $process->setInput($plainText);

        try {
            $process->mustRun();

            return $process->getOutput();
        } catch (ProcessFailedException $e) {
            $this->logger->warning('PlainTextConverter: pandoc process failed.', [
                'error' => $e->getMessage(),
            ]);

            return null;
        } catch (\Throwable $e) {
            $this->logger->warning('PlainTextConverter: unexpected error during pandoc conversion.', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
