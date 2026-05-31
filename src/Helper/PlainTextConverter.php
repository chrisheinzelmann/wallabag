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

        $plainText = strip_tags($text);

        if ('' === trim($plainText)) {
            return null;
        }

        if (!class_exists($processClass)) {
            $this->logger->warning('PlainTextConverter: Symfony Process component is unavailable.');

            return null;
        }

        $process = new $processClass([$this->pandocBinary, '-f', 'markdown', '-t', 'html']);
        $process->setInput($plainText);

        try {
            $process->mustRun();

            return $process->getOutput();
        } catch (\Throwable $e) {
            $this->logger->warning('PlainTextConverter: unexpected error during pandoc conversion.', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
