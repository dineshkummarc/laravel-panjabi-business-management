<?php

namespace App\Services;

use ImranDev\UnicodePdf\Engines\NativeEngine;
use ImranDev\UnicodePdf\Native\FontLibrary;
use ImranDev\UnicodePdf\Native\HtmlLayouter;

/** Uses the package layout/writer with bundled fonts, without request-time downloads. */
class LocalPdfEngine extends NativeEngine
{
    public function output(): string
    {
        $prepared = $this->getPreparedContent();
        [$width,$height] = $this->pageDimensions();
        $margins = $this->marginsInPoints();
        $library = new FontLibrary($this->fontManager, [resource_path('fonts')]);
        $families = array_values(array_filter(array_merge([$this->primaryFont], $this->fallbackFonts, $prepared['resolved_fonts'])));
        $commands = (new HtmlLayouter)->layout(html: $prepared['html'], fonts: $library, pageWidth: $width, pageHeight: $height, marginLeft: $margins['left'], marginTop: $margins['top'], marginRight: $margins['right'], marginBottom: $margins['bottom'], fontFamilies: $families, direction: $prepared['detected_direction']);

        return $this->buildPdf($commands, $width, $height, $margins, $prepared['html']);
    }
}
