<?php

declare(strict_types=1);

/**
 * Gemeinsame Hilfen der Kacheln: setzt gemeinsames Stylesheet, Kachel-HTML des Moduls und Startdaten zusammen.
 */
trait PiHoleTile
{
    /**
     * HTML der Kachel. Die Datei module.html des Moduls definiert handleMessage(json), die Startdaten übergibt sie hier.
     *
     * @param string $directory Ordner des Moduls mit der module.html
     * @param array  $data      Werte, die die Kachel anzeigt
     */
    protected function RenderTile(string $directory, array $data): string
    {
        $css = file_get_contents(__DIR__ . '/tile.css');
        $js = file_get_contents(__DIR__ . '/tile.js');
        $html = file_get_contents($directory . '/module.html');
        // json_encode zweimal: Die Kachel erhält einen JSON-Text, wie bei UpdateVisualizationValue
        $init = '<script>handleMessage(' . json_encode(json_encode($data)) . ');</script>';

        return '<style>' . $css . '</style><script>' . $js . '</script>' . $html . $init;
    }

    /** Schickt neue Werte an alle geöffneten Kacheln der Instanz. */
    protected function PushTile(array $data): void
    {
        $this->UpdateVisualizationValue(json_encode($data));
    }
}
