<?php
// Deterministic BMPM corpus: surnames plus generated strings over letters and
// digraphs that the rule contexts and language-guessing patterns key on.
function bmpm_corpus(int $count, int $seed): array {
    $names = ['Schwarzenegger', 'Moskowitz', 'Wisniewski', 'Przybylski', 'Thibodeaux',
        'Tchaikovsky', 'Lindqvist', 'Gonçalves', 'Łukasiewicz', 'Dvořák', 'Nuñez',
        'Großmann', 'Иванов', 'Kovács', 'Müller', 'Ångström', 'Øster', 'Şahin',
        'Ferreira', 'Giordano', 'Rodriguez', 'Yanez', 'Jaques', 'Xavier', 'Csaba',
        'Szczepański', 'Dzieduszycki', 'Ciccone', 'Ghirlandaio', 'Sciacca'];
    $pieces = ['a', 'e', 'i', 'o', 'u', 'y', 'b', 'c', 'd', 'g', 'h', 'j', 'k', 'l', 'm',
        'n', 'p', 'q', 'r', 's', 't', 'v', 'w', 'x', 'z', 'ch', 'sch', 'sz', 'cz', 'tz',
        'gh', 'gn', 'gu', 'qu', 'ph', 'th', 'zh', 'ss', 'll', 'rz', 'dz', 'ou', 'ei',
        'ae', 'ue', 'ö', 'ü', 'ä', 'é', 'è', 'á', 'ó', 'í', 'ñ', 'ç', 'ł', 'ś', 'ż',
        'ő', 'ű', 'ř', 'š', 'č', 'ă', 'ş', 'ț', 'и', 'в', 'а', 'н', 'ш', 'щ', ' ',
        '-', "'", 'de ', 'van ', 'bar '];
    mt_srand($seed);
    $out = $names;
    $np = count($pieces);
    while (count($out) < $count) {
        $len = mt_rand(1, 6);
        $s = '';
        for ($i = 0; $i < $len; $i++) $s .= $pieces[mt_rand(0, $np - 1)];
        $out[] = $s;
    }
    return $out;
}

function bmpm_corpus_digest(array $corpus): string {
    $h = hash_init('sha256');
    foreach ([BMPM_GENERIC, BMPM_ASHKENAZI, BMPM_SEPHARDIC] as $nt) {
        foreach ([BMPM_APPROX, BMPM_EXACT] as $acc) {
            foreach ($corpus as $s) {
                try {
                    $r = bmpm($s, $nt, $acc);
                } catch (\Throwable $e) {
                    $r = get_class($e) . ':' . $e->getMessage();
                }
                hash_update($h, "$nt/$acc/$s=$r\n");
            }
        }
    }
    $langs = [BMPM_GENERIC => ['arabic', 'cyrillic', 'czech', 'dutch', 'english', 'french',
            'german', 'greek', 'greeklatin', 'hebrew', 'hungarian', 'italian', 'polish',
            'portuguese', 'romanian', 'russian', 'spanish', 'turkish'],
        BMPM_ASHKENAZI => ['cyrillic', 'english', 'french', 'german', 'hebrew', 'hungarian',
            'polish', 'romanian', 'russian', 'spanish'],
        BMPM_SEPHARDIC => ['french', 'hebrew', 'italian', 'portuguese', 'spanish']];
    $forced = array_slice($corpus, 0, 300);
    foreach ($langs as $nt => $list) {
        foreach ($list as $lang) {
            foreach ($forced as $s) {
                try {
                    $r = bmpm($s, $nt, BMPM_APPROX, $lang);
                } catch (\Throwable $e) {
                    $r = get_class($e) . ':' . $e->getMessage();
                }
                hash_update($h, "$nt/$lang/$s=$r\n");
            }
        }
    }
    return hash_final($h);
}
