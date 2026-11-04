<?php
/* E-mail: adapter MIME hlavicek pro Webklex; poskozeny encoded-word nesmi vymazat puvodni predmet nebo jmeno. */
declare(strict_types=1);

/** Knihovna ma vlastni adapter, abychom nemenili vendor ani neprovadeli opravy az po ztrate textu. */
final class EmailMimeHeaderDecoder extends Webklex\PHPIMAP\Decoder\HeaderDecoder
{
    public function decode(array|string|null $value, ?string $encoding=null): mixed
    {
        if(is_array($value))return array_map(fn($item)=>$this->decode($item),$value);
        return $value===null?null:em_mime_hlavicka($value);
    }
}
