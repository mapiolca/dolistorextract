#!/usr/bin/env python3
"""Check catalogs, formats, template DOM and literal/finite dynamic translation keys.
DOLIBARR_TEST_ROOT optionally resolves native keys from the selected checkout.
This is a source check, not a browser or runtime translation test.
"""
from pathlib import Path
import os, re, sys
from html.parser import HTMLParser
from collections import Counter
ROOT=Path(__file__).resolve().parents[1]
LANGS=('fr_FR','en_US','es_ES','it_IT','de_DE')
errors=[]
def catalog(path):
    result={}
    for n,line in enumerate(path.read_text(encoding='utf-8').splitlines(),1):
        if not line.strip() or line.lstrip().startswith('#'):continue
        if '=' not in line:errors.append(f'{path}:{n}: invalid entry');continue
        key,value=(x.strip() for x in line.split('=',1))
        if key in result:errors.append(f'{path}:{n}: duplicate {key}')
        if not value:errors.append(f'{path}:{n}: empty {key}')
        result[key]=value
    return result
cats={lang:catalog(ROOT/'langs'/lang/'dolistorextract.lang') for lang in LANGS}
keys=set().union(*(set(c) for c in cats.values()))
# Resolve the settings loop from its production language map, not just the prefix.
welcome_source=(ROOT/'class/dolistoreWelcomeMail.class.php').read_text(encoding='utf-8')
language_map=re.search(r'public const LANGUAGES\s*=\s*array\((.*?)\);',welcome_source,re.S)
welcome_languages=dict(re.findall(r"['\"]([a-z]{2}_[A-Z]{2})['\"]\s*=>\s*['\"]([A-Z]{2})['\"]",language_map.group(1))) if language_map else {}
if set(welcome_languages)!=set(LANGS):errors.append('Welcome language map does not match the checked catalogs')
dynamic_values={'$language':tuple(welcome_languages),'$suffix':tuple(welcome_languages.values())}
def translation_keys(source,path):
    """Expand supported finite concatenations; fail on unchecked dynamic suffixes."""
    found=set()
    for match in re.finditer(r"->trans(?:noentitiesnoconv|noentities|noconv)?\(\s*(['\"])([^'\"\n]+)\1",source):
        key=match.group(2)
        tail=source[match.end():].lstrip()
        if tail.startswith('.'):
            suffix=re.match(r'\.\s*(\$[A-Za-z_]\w*)\s*[,)]',tail)
            if not suffix or suffix.group(1) not in dynamic_values:
                errors.append(f'{path.relative_to(ROOT)}: unchecked dynamic translation {key}')
                continue
            found.update(key+value for value in dynamic_values[suffix.group(1)])
        else:
            found.add(key)
    return found
formats=lambda text:Counter(re.findall(r'%(?:\d+\$)?[-+0 #]*(?:\d+)?(?:\.\d+)?[sdif]|__[A-Z0-9_]+__',text))
for lang,cat in cats.items():
    for key in keys-set(cat):errors.append(f'{lang}: missing {key}')
    for key,value in cat.items():
        if formats(value)!=formats(cats['en_US'].get(key,'')):errors.append(f'{lang}: format mismatch {key}')
class Structure(HTMLParser):
    def __init__(self):super().__init__(convert_charrefs=True);self.structure=[];self.text=[]
    def handle_starttag(self,tag,attrs):self.structure.append(('start',tag,tuple(attrs)))
    def handle_endtag(self,tag):self.structure.append(('end',tag))
    def handle_comment(self,data):
        if 'PRODUCTS_' in data:self.structure.append(('comment',data))
    def handle_data(self,data):
        if data.strip():self.text.append(data.strip())
parsers={}
for lang in LANGS:
    content=(ROOT/'core/tpl/welcome'/f'{lang}.html').read_text()
    parser=Structure();parser.feed(content);parsers[lang]=parser
    if parser.structure != parsers['fr_FR'].structure:errors.append(f'{lang}: welcome HTML structure changed')
    if formats(content)!=formats((ROOT/'core/tpl/welcome/fr_FR.html').read_text()):errors.append(f'{lang}: welcome substitutions changed')
    if len(parser.text)!=len(parsers['fr_FR'].text):errors.append(f'{lang}: missing welcome paragraphs')
core=Path(os.environ['DOLIBARR_TEST_ROOT']) if 'DOLIBARR_TEST_ROOT' in os.environ else None
native={lang:set() for lang in LANGS}
if core:
    for lang in LANGS:
        for file in (core/'langs'/lang).glob('*.lang'):
            native[lang].update(line.split('=',1)[0].strip() for line in file.read_text(errors='replace').splitlines() if '=' in line and not line.lstrip().startswith('#'))
for path in ROOT.rglob('*.php'):
    if any(part in ('include','test','vendor') for part in path.relative_to(ROOT).parts):continue
    text=path.read_text()
    for key in translation_keys(text,path):
        for lang in LANGS:
            if key not in cats[lang] and (key.startswith('Dolistore') or (core and key not in native[lang])):errors.append(f'{path.relative_to(ROOT)}: {lang}: unresolved {key}')
    for key in re.findall(r'[\'"]((?:Dolistore|Notify_DOLISTORE)[A-Za-z0-9_]+)[\'"]',text):
        if key in keys:continue
        if key in ('Dolistorextract','DolistoreOrder','DolistoreOrderLine','DolistoreInvoiceBatch'):continue
        # Translation-looking keys outside calls (status maps, fields, cron definitions).
        if not key.isupper() and not key.endswith('_'):errors.append(f'{path.relative_to(ROOT)}: missing dynamic/literal key {key}')
# Page-level source coverage: only explicitly loaded catalogs plus main.inc defaults.
# Shared tab builders load their declared catalogs before rendering their labels.
if core:
    for path in list(ROOT.glob('*.php')) + list((ROOT/'admin').glob('*.php')):
        source=path.read_text()
        loaded={'main','errors','dict'}
        for args in re.findall(r"->loadLangs\(array\((.*?)\)\)", source, re.S):
            loaded.update(re.findall(r"['\"]([A-Za-z0-9_@]+)['\"]",args))
        loaded.update(re.findall(r"->load\(['\"]([^'\"]+)",source))
        if 'PrepareHead(' in source:
            loaded.update(('admin','agenda','bills','companies','dolistorextract@dolistorextract'))
        for lang in LANGS:
            available=set()
            for name in loaded:
                file=ROOT/'langs'/lang/'dolistorextract.lang' if '@' in name else core/'langs'/lang/(name+'.lang')
                if file.exists():
                    available.update(line.split('=',1)[0].strip() for line in file.read_text().splitlines() if '=' in line and not line.lstrip().startswith('#'))
            for key in translation_keys(source,path):
                if key not in available:
                    errors.append(f'{path.relative_to(ROOT)}: {lang}: catalog not loaded for {key}')
    # Native permission naming convention and runtime status/source families.
    for lang in LANGS:
        for number in range(45003201,45003209):
            if f'Permission{number}' not in cats[lang]:errors.append(f'{lang}: missing permission {number}')
if errors:
    print('\n'.join(sorted(set(errors))));sys.exit(1)
print(f'OK: {len(keys)} keys × {len(LANGS)} languages; formats, nonempty values, duplicates, literal/dynamic keys and five welcome HTML structures.')
