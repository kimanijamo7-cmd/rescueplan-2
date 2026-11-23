<?php
declare(strict_types=1);

/**
 * Three-stage deduplication pipeline: blocking, similarity scoring, cluster fusion.
 * Ported from the Python reference engine so both builds produce identical clusters.
 */

function soundex_code(string $word): string
{
    $word = strtoupper(preg_replace('/[^A-Za-z]/', '', $word) ?? '');
    if ($word === '') {
        return '';
    }
    $codes = [
        'B' => '1', 'F' => '3', 'P' => '1', 'V' => '1',
        'C' => '2', 'G' => '2', 'J' => '2', 'K' => '2', 'Q' => '2', 'S' => '2', 'X' => '2', 'Z' => '2',
        'D' => '3', 'T' => '3',
        'L' => '4',
        'M' => '5', 'N' => '5',
        'R' => '6',
    ];
    $result = $word[0];
    $previous = $codes[$word[0]] ?? null;
    $length = strlen($word);
    for ($index = 1; $index < $length; $index++) {
        $letter = $word[$index];
        $code = $codes[$letter] ?? null;
        if ($code !== null && $code !== $previous) {
            $result .= $code;
        }
        if ($letter !== 'H' && $letter !== 'W') {
            $previous = $code;
        }
    }
    return substr($result . '000', 0, 4);
}

function jaro_similarity(string $s1, string $s2): float
{
    if ($s1 === $s2) {
        return 1.0;
    }
    $length1 = strlen($s1);
    $length2 = strlen($s2);
    if ($length1 === 0 || $length2 === 0) {
        return 0.0;
    }
    $window = intdiv(max($length1, $length2), 2) - 1;
    $flags1 = array_fill(0, $length1, false);
    $flags2 = array_fill(0, $length2, false);
    $matches = 0;

    for ($i = 0; $i < $length1; $i++) {
        $start = max(0, $i - $window);
        $end = min($i + $window + 1, $length2);
        for ($k = $start; $k < $end; $k++) {
            if (!$flags2[$k] && $s1[$i] === $s2[$k]) {
                $flags1[$i] = true;
                $flags2[$k] = true;
                $matches++;
                break;
            }
        }
    }

    if ($matches === 0) {
        return 0.0;
    }

    $transpositions = 0;
    $k = 0;
    for ($i = 0; $i < $length1; $i++) {
        if (!$flags1[$i]) {
            continue;
        }
        while (!$flags2[$k]) {
            $k++;
        }
        if ($s1[$i] !== $s2[$k]) {
            $transpositions++;
        }
        $k++;
    }
    $transpositions = intdiv($transpositions, 2);

    return ($matches / $length1 + $matches / $length2 + ($matches - $transpositions) / $matches) / 3.0;
}

function jaro_winkler(string $s1, string $s2, float $boost = 0.1): float
{
    $s1 = mb_strtolower(trim($s1));
    $s2 = mb_strtolower(trim($s2));
    $score = jaro_similarity($s1, $s2);
    $prefix = 0;
    $limit = min(4, strlen($s1), strlen($s2));
    for ($i = 0; $i < $limit; $i++) {
        if ($s1[$i] !== $s2[$i]) {
            break;
        }
        $prefix++;
    }
    return round($score + $prefix * $boost * (1 - $score), 4);
}

function blocking_key(array $beneficiary): string
{
    $words = preg_split('/\s+/', trim((string) $beneficiary['full_name'])) ?: [];
    $surname = $words ? end($words) : '';
    return soundex_code($surname) . '::' . mb_strtolower(trim((string) $beneficiary['camp_location']));
}

function build_blocks(array $beneficiaries): array
{
    $blocks = [];
    foreach ($beneficiaries as $record) {
        $blocks[blocking_key($record)][] = $record;
    }
    return $blocks;
}

function score_pair(array $b1, array $b2): float
{
    $name_score = jaro_winkler((string) $b1['full_name'], (string) $b2['full_name']);
    $id_score = jaro_winkler((string) $b1['national_id'], (string) $b2['national_id']);
    return round($name_score * NAME_WEIGHT + $id_score * NATIONAL_ID_WEIGHT, 4);
}

final class UnionFind
{
    private array $parent = [];

    public function __construct(array $ids)
    {
        foreach ($ids as $id) {
            $this->parent[$id] = $id;
        }
    }

    public function find(int $x): int
    {
        while ($this->parent[$x] !== $x) {
            $this->parent[$x] = $this->parent[$this->parent[$x]];
            $x = $this->parent[$x];
        }
        return $x;
    }

    public function union(int $a, int $b): void
    {
        $rootA = $this->find($a);
        $rootB = $this->find($b);
        if ($rootA !== $rootB) {
            $this->parent[$rootB] = $rootA;
        }
    }
}

/** Returns clusters: [['beneficiary_ids' => [...], 'similarity_score' => float], ...] */
function run_pipeline(array $beneficiaries, float $threshold = SIMILARITY_THRESHOLD): array
{
    $by_id = [];
    foreach ($beneficiaries as $record) {
        $by_id[(int) $record['beneficiary_id']] = $record;
    }
    $finder = new UnionFind(array_keys($by_id));
    $best_score = [];

    foreach (build_blocks($beneficiaries) as $block) {
        $count = count($block);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $a = $block[$i];
                $b = $block[$j];
                $score = score_pair($a, $b);
                if ($score >= $threshold) {
                    $finder->union((int) $a['beneficiary_id'], (int) $b['beneficiary_id']);
                    $root = $finder->find((int) $a['beneficiary_id']);
                    $best_score[$root] = max($best_score[$root] ?? 0.0, $score);
                }
            }
        }
    }

    $groups = [];
    foreach (array_keys($by_id) as $id) {
        $groups[$finder->find($id)][] = $id;
    }

    $clusters = [];
    foreach ($groups as $root => $member_ids) {
        if (count($member_ids) > 1) {
            sort($member_ids);
            $clusters[] = [
                'beneficiary_ids' => $member_ids,
                'similarity_score' => round($best_score[$root] ?? 0.0, 4),
            ];
        }
    }
    usort($clusters, fn(array $a, array $b) => $b['similarity_score'] <=> $a['similarity_score']);
    return $clusters;
}
