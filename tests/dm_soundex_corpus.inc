<?php
// Deterministic DM Soundex corpus: surnames plus generated strings built from
// every rule pattern, folded and unfolded letters, and whitespace, so pattern
// prefixes run off the end of the input and branches merge and fan out.
function dm_corpus(int $count, int $seed): array {
    $names = ['Schwarzenegger', 'Moskowitz', 'Wisniewski', 'Przybylski', 'Tchaikovsky',
        'Szczepański', 'Dzieduszycki', 'Łukasiewicz', 'Dvořák', 'Müller', 'Gutiérrez',
        'Ţuţea', 'Țepeș', 'Kowalczyk', 'Abramowitz', 'Rosenberg', 'Washington', "O'Brien",
        'Jackson', 'Zhdanov', 'Fitzgerald', 'Christopher', 'Bartholomew', 'Strzelecki'];
    $tok = ['a', 'e', 'i', 'o', 'u', 'b', 'c', 'd', 'f', 'g', 'h', 'j', 'k', 'l', 'm', 'n',
        'p', 'q', 'r', 's', 't', 'v', 'w', 'x', 'y', 'z', 'ţ', 'ț', 'ę', 'ą', 'schtsch',
        'schtsh', 'schtch', 'shtch', 'shtsh', 'stsch', 'ttsch', 'zhdzh', 'shch', 'scht',
        'schd', 'stch', 'strz', 'strs', 'stsh', 'szcz', 'szcs', 'ttch', 'tsch', 'ttsz', 'zdzh',
        'zsch', 'chs', 'csz', 'czs', 'drz', 'drs', 'dsh', 'dsz', 'dzh', 'dzs', 'sch', 'sht',
        'szt', 'shd', 'szd', 'tch', 'trz', 'trs', 'tsh', 'tts', 'ttz', 'tzs', 'tsz', 'zdz',
        'zhd', 'zsh', 'ai', 'aj', 'ay', 'au', 'cz', 'cs', 'ds', 'dz', 'dt', 'ei', 'ej', 'ey',
        'eu', 'fb', 'ia', 'ie', 'io', 'iu', 'ks', 'kh', 'mn', 'nm', 'oi', 'oj', 'oy', 'pf',
        'ph', 'sh', 'sc', 'st', 'sd', 'sz', 'th', 'ts', 'tc', 'tz', 'ui', 'uj', 'uy', 'ue',
        'zd', 'zh', 'zs', 'ch', 'ck', 'rs', 'rz', 'SCH', 'Ch', 'Ö', 'ü', 'ł', 'ñ', 'ç', 'ß',
        'é', 'Å', 'β', 'ж', ' ', "\t", "\u{2007}", "\u{3000}", "'", '-', '1'];
    mt_srand($seed);
    $out = $names;
    $n = count($tok);
    for ($i = 0; $i < $count; $i++) {
        // Every tenth string is long enough to fan out past the hashed-set threshold.
        $len = $i % 10 === 9 ? mt_rand(20, 60) : mt_rand(1, 14);
        $s = '';
        for ($j = 0; $j < $len; $j++) {
            $s .= $tok[mt_rand(0, $n - 1)];
        }
        $out[] = $s;
    }
    return $out;
}

function dm_corpus_digest(array $corpus): string {
    $h = hash_init('sha256');
    $prev = '';
    foreach ($corpus as $s) {
        try {
            hash_update($h, implode(',', dm_soundex($s)) . '|');
            hash_update($h, (dm_soundex_match($prev, $s) ? '1' : '0') . "\n");
        } catch (\ValueError $e) {
            hash_update($h, "E\n");
        }
        $prev = $s;
    }
    return hash_final($h);
}
