<?php
/* E-mail: samostatne HTML/CSS prijate posty s jednoznacnym UTF-8, izolovane styly a zachovani odkazu, tabulek i CID obrazku. */
declare(strict_types=1);

/** Hodnoty CSS smi popisovat vzhled, nikoli sitove zdroje, skripty nebo presah mimo dokument zpravy. */
function em_zobrazeni_deklarace($block): string
{
    $allowed=explode(' ','color background background-color font font-family font-size font-weight font-style line-height letter-spacing text-align text-decoration text-transform white-space word-break overflow-wrap vertical-align display visibility opacity width min-width max-width height min-height max-height margin margin-top margin-right margin-bottom margin-left padding padding-top padding-right padding-bottom padding-left border border-top border-right border-bottom border-left border-color border-width border-style border-radius border-collapse border-spacing table-layout list-style-type list-style-position float clear box-sizing');
    $out=[];
    // Dompdf pouziva parser rady 8 i 9; modul nesmi vyzadovat jeho nevyzadanou aktualizaci.
    $declarations=method_exists($block,'getDeclarations')?$block->getDeclarations():$block->getRules();
    foreach($declarations as $rule) {
        $name=strtolower(method_exists($rule,'getPropertyName')?$rule->getPropertyName():$rule->getRule());$value=$rule->getValue();
        $value=is_object($value)?$value->render(Sabberworm\CSS\OutputFormat::createCompact()):(string)$value;
        if(!in_array($name,$allowed,true) || strlen($value)>500 || preg_match('/[\\\\<>@{};]|(?:url|expression|attr|var|image-set|paint)\s*\(/i',$value))continue;
        if(!preg_match('/^[a-z0-9#%.,\s()\x27"+\-\/:]*$/iD',$value))continue;
        // Funkce s ciselnymi argumenty nemohou nacist zdroj ani spustit kod.
        if(preg_match_all('/([a-z-]+)\s*\(/i',$value,$functions))foreach($functions[1] as $fn)if(!in_array(strtolower($fn),['rgb','rgba','hsl','hsla','calc','min','max','clamp'],true))continue 2;
        $out[]=$name.':'.$value;
    }
    return implode(';',$out);
}

/** Pouzijeme dostupny parser CSS, nikoli deleni hodnot stredniky nebo prepisovani celeho HTML regexem. */
function em_zobrazeni_styl(string $style): string
{
    if($style==='' || strlen($style)>8192)return '';
    try {$doc=(new Sabberworm\CSS\Parser('x{'.$style.'}'))->parse();$blocks=$doc->getAllDeclarationBlocks();return count($blocks)===1?em_zobrazeni_deklarace($blocks[0]):'';}
    catch(Throwable){return '';}
}

/** Styly prijate zpravy zustanou uvnitr jejiho obalu i po pripojeni citace do odesilaneho emailu. */
function em_zobrazeni_css($list, int $depth=0): string
{
    if($depth>8)return '';$out='';
    foreach($list->getContents() as $block) {
        if($block instanceof Sabberworm\CSS\RuleSet\DeclarationBlock) {
            $rules=em_zobrazeni_deklarace($block);if($rules==='')continue;$selectors=[];
            foreach($block->getSelectors() as $selector) {
                $s=$selector->getSelector();if(strlen($s)>300 || !preg_match('/^[a-z0-9_\-\s.#*>+~:\[\]="\x27()|^$]+$/iD',$s))continue;
                $s=preg_replace('/(?<![\w.-])(?:html|body)(?![\w-])/i','.em-mail',$s);
                $selectors[]=str_starts_with($s,'.em-mail')?$s:'.em-mail '.$s;
            }
            if($selectors)$out.=implode(',',$selectors).'{'.$rules.'}';
        } elseif($block instanceof Sabberworm\CSS\CSSList\AtRuleBlockList && strtolower($block->atRuleName())==='media') {
            $args=$block->atRuleArgs();if(preg_match('/^[a-z0-9\s():.,\/-]+$/iD',$args))$out.='@media '.$args.'{'.em_zobrazeni_css($block,$depth+1).'}';
        }
    }
    return $out;
}

/** Bezpecne prijate HTML neni podmnozina editoru: zachovava tabulky a vzhled, aktivni prvky meni na inertni obsah. */
function em_zobrazeni_html(string $html, bool $remote=false, bool $mimeCid=false): string
{
    if($html==='')return '';em_knihovny();
    $doc=new DOMDocument();$old=libxml_use_internal_errors(true);
    try {$doc->loadHTML('<?xml encoding="UTF-8">'.em_html_utf8($html),LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);}
    finally {libxml_clear_errors();libxml_use_internal_errors($old);}
    $css='';foreach(iterator_to_array($doc->getElementsByTagName('style')) as $style) {
        if(strlen($style->textContent)<=131072)try{$css.=em_zobrazeni_css((new Sabberworm\CSS\Parser($style->textContent))->parse());}catch(Throwable){}
        $style->parentNode?->removeChild($style);
    }
    $allowed=explode(' ','p br div span strong b em i u s del ins blockquote pre code ul ol li table caption colgroup col thead tbody tfoot tr td th hr h1 h2 h3 h4 h5 h6 a img center address dl dt dd sub sup');
    $walk=function(DOMNode $node)use(&$walk,$doc,$allowed,$remote,$mimeCid):void {
        foreach(iterator_to_array($node->childNodes) as $child) {
            if(!$child instanceof DOMElement){if(!$child instanceof DOMText)$node->removeChild($child);continue;}
            $tag=strtolower($child->tagName);
            if(in_array($tag,['script','iframe','object','embed','link','meta','base','svg','math','video','audio','source'],true)){$node->removeChild($child);continue;}
            $attrs=[];foreach($child->attributes as $attr)$attrs[strtolower($attr->name)]=$attr->value;
            if($tag==='font') {
                $span=$doc->createElement('span');while($child->firstChild)$span->appendChild($child->firstChild);$node->replaceChild($span,$child);$child=$span;$tag='span';
                $attrs['style']=($attrs['style']??'').';color:'.($attrs['color']??'inherit').';font-family:'.($attrs['face']??'inherit');
            }
            $walk($child);
            if(!in_array($tag,$allowed,true)) {
                // Text formulare ci tlacitka se zachova, jejich akce se nespousti.
                if(in_array($tag,['input','textarea','select'],true) && !empty($attrs['value']))$node->insertBefore($doc->createTextNode($attrs['value']),$child);
                while($child->firstChild)$node->insertBefore($child->firstChild,$child);$node->removeChild($child);continue;
            }
            foreach(iterator_to_array($child->attributes) as $attr)$child->removeAttribute($attr->name);
            $style=em_zobrazeni_styl($attrs['style']??'');if($style!=='')$child->setAttribute('style',$style);
            foreach(['class','id'] as $name)if(isset($attrs[$name]) && preg_match('/^[a-z0-9_\-\s]+$/iD',$attrs[$name]))$child->setAttribute($name,substr($attrs[$name],0,1000));
            foreach(['title','alt','lang','dir'] as $name)if(isset($attrs[$name]))$child->setAttribute($name,substr($attrs[$name],0,1000));
            foreach(['width','height','cellpadding','cellspacing','border','colspan','rowspan','span'] as $name)if(isset($attrs[$name])&&preg_match('/^\d{1,4}%?$/D',$attrs[$name]))$child->setAttribute($name,$attrs[$name]);
            if(isset($attrs['bgcolor'])&&preg_match('/^(#[a-f0-9]{3}(?:[a-f0-9]{3})?|[a-z]{1,20})$/iD',$attrs['bgcolor']))$child->setAttribute('bgcolor',$attrs['bgcolor']);
            if(isset($attrs['align'])&&in_array(strtolower($attrs['align']),['left','right','center','justify'],true))$child->setAttribute('align',$attrs['align']);
            if($tag==='a' && preg_match('~^(https?://|mailto:)[^\x00-\x20]+$~i',$attrs['href']??'')) {
                $child->setAttribute('href',$attrs['href']);$child->setAttribute('target','_blank');$child->setAttribute('rel','noopener noreferrer');
            }
            if($tag==='img') {
                $src=$attrs['src']??'';
                if(preg_match('/^cid:([^\x00-\x20]+)$/iD',$src,$m)) {
                    $cid=rawurldecode($m[1]);$child->setAttribute($mimeCid?'src':'data-em-cid',$mimeCid?'cid:'.$cid:$cid);
                } elseif($remote && preg_match('~^https://[^\x00-\x20]+$~iD',$src)){$child->setAttribute('src',$src);$child->setAttribute('referrerpolicy','no-referrer');}
                if(!$child->hasAttribute('alt'))$child->setAttribute('alt','[obrázek]');
            }
        }
    };
    $body=$doc->getElementsByTagName('body')->item(0);if(!$body)return '';
    $bodyStyle=em_zobrazeni_styl($body->getAttribute('style').($body->hasAttribute('bgcolor')?';background-color:'.$body->getAttribute('bgcolor'):''));
    $bodyClass=preg_match('/^[a-z0-9_\-\s]+$/iD',$body->getAttribute('class'))?' '.$body->getAttribute('class'):'';
    $walk($body);$out='';foreach($body->childNodes as $child)$out.=$doc->saveHTML($child);
    return ($css!==''?'<style>'.$css.'</style>':'').'<div class="em-mail'.htmlspecialchars($bodyClass,ENT_QUOTES,'UTF-8').'"'.($bodyStyle!==''?' style="'.htmlspecialchars($bodyStyle,ENT_QUOTES,'UTF-8').'"':'').'>'.$out.'</div>';
}

/** Textovou alternativu citace odvozujeme bez omezujiciho filtru editoru. */
function em_zobrazeni_text(string $html): string
{
    $html=preg_replace('~<style\b[^>]*>.*?</style>~is','',$html);
    $html=preg_replace('~<(?:br\b[^>]*|/p|/div|/tr|/li|/blockquote)>~i',"\n",$html);
    return trim(html_entity_decode(strip_tags($html),ENT_QUOTES|ENT_HTML5,'UTF-8'));
}
