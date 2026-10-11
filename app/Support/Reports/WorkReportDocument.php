<?php

namespace App\Support\Reports;

use JsonException;

/**
 * Builds a frozen work register from database facts, not language-model output.
 * The narrative is optional context; every date, row and total is authoritative.
 */
final class WorkReportDocument
{
    /** @throws JsonException */
    public function render(string $context, string $narrative, string $language): string
    {
        $data = json_decode($context, true, 512, JSON_THROW_ON_ERROR);
        $worklogs = $data['worklogs'] ?? null;
        if (! is_array($worklogs)) {
            throw new \InvalidArgumentException('Work Report requires Worklogs as a source.');
        }

        // Most providers return JSON, but some include a JSON code fence or encode
        // an integer worklog id as a JSON string. Both are safe to normalize.
        $payload = trim($narrative);
        if (preg_match('/\A```(?:json)?\s*(.*?)\s*```\z/is', $payload, $matches)) {
            $payload = $matches[1];
        }
        $decoded = json_decode($payload, true);
        if (! is_array($decoded) || ! is_string($decoded['summary'] ?? null) || ! is_array($decoded['rows'] ?? null)) {
            throw new \InvalidArgumentException('Invalid work report translation from AI. Try generating again.');
        }

        $entries = $worklogs['details'] ?? [];
        $expected = [];
        foreach ($entries as $entry) {
            $expected[(int) $entry['id']] = $entry;
        }

        $translations = [];
        $list = array_is_list($decoded['rows']);
        foreach ($decoded['rows'] as $key => $row) {
            if (! is_array($row)) {
                throw new \InvalidArgumentException('Invalid translated worklog rows. Try again.');
            }

            // A keyed object {"123": {...}} is also accepted, but list positions
            // are never treated as entry ids.
            $rawId = $row['id'] ?? ($list ? null : $key);
            $id = (is_int($rawId) || (is_string($rawId) && preg_match('/\A[1-9][0-9]*\z/D', $rawId)))
                ? filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
                : false;
            $title = $row['task_title'] ?? $row['title'] ?? null;
            $outcome = $row['outcome'] ?? $row['description'] ?? null;

            if ($id === false || ! isset($expected[$id]) || isset($translations[$id])
                || ! is_string($title) || trim($title) === '' || mb_strlen($title) > 600
                || ($outcome !== null && (! is_string($outcome) || mb_strlen($outcome) > 2000))
                || ($expected[$id]['description'] !== null && (! is_string($outcome) || trim($outcome) === ''))) {
                throw new \InvalidArgumentException('Invalid translated worklog rows. Try again.');
            }

            $translations[$id] = ['task_title' => trim($title), 'outcome' => $outcome === null ? null : trim($outcome)];
        }
        if (count($translations) !== count($expected)) {
            throw new \InvalidArgumentException('Incomplete worklog translation. Try again.');
        }

        $labels = $this->labels($language);
        $primaryLanguage = strtolower(explode('-', $language)[0]);
        if (! in_array($primaryLanguage, ['en', 'pl', 'de', 'fr', 'es', 'it', 'pt', 'nl', 'uk', 'ja', 'ko', 'zh'], true)
            && (! is_array($decoded['labels'] ?? null)
                || count(array_filter($decoded['labels'], 'is_string')) < count($labels))) {
            throw new \InvalidArgumentException('Incomplete report labels translation. Try again.');
        }
        if (is_array($decoded['labels'] ?? null)) {
            foreach (array_keys($labels) as $key) {
                // For built-in locales the no-description note must remain factual, not AI-authored.
                if ($key === 'missing' && in_array($primaryLanguage, ['en', 'pl', 'de', 'fr', 'es', 'it', 'pt', 'nl', 'uk', 'ja', 'ko', 'zh'], true)) {
                    continue;
                }
                if (isset($decoded['labels'][$key]) && is_string($decoded['labels'][$key]) && trim($decoded['labels'][$key]) !== '') {
                    $labels[$key] = mb_substr(trim($decoded['labels'][$key]), 0, 120);
                }
            }
        }

        $lines = [
            '# '.$labels['title'],
            '',
            '**'.$labels['project'].':** '.$this->cell((string) $data['project']),
            '**'.$labels['period'].':** '.$data['period_start'].' – '.$data['period_end'],
            '',
            '## '.$labels['summary'],
            '',
            trim($decoded['summary']),
            '',
            '## '.$labels['entries'],
            '',
        ];

        if (count($entries) === 0) {
            $lines[] = $labels['empty'];
        } else {
            $lines[] = '| '.$labels['date'].' | '.$labels['task'].' | '.$labels['outcome'].' | '.$labels['contributor'].' | '.$labels['time'].' |';
            $lines[] = '| --- | --- | --- | --- | ---: |';
            foreach ($entries as $entry) {
                $translated = $translations[$entry['id']];
                $task = $this->cell((string) $entry['task']).' · '.$this->cell($translated['task_title']);
                $description = $entry['description'] === null ? $labels['missing'] : $translated['outcome'];
                $lines[] = '| '.$entry['date'].' | '.$task.' | '.$this->cell((string) $description)
                    .' | '.$this->cell((string) ($entry['contributor'] ?? '—')).' | '.$this->duration((int) $entry['minutes']).' |';
            }
        }

        $lines[] = '';
        $lines[] = '## '.$labels['totals'];
        $lines[] = '';
        $lines[] = '- **'.$labels['total'].':** '.$this->duration((int) $worklogs['total_minutes']);
        $lines[] = '- **'.$labels['creative'].':** '.$this->duration((int) $worklogs['creative_minutes']);
        $lines[] = '- **'.$labels['count'].':** '.(int) $worklogs['entries'];

        return implode("\n", $lines);
    }

    /** @return array<string, string> */
    private function labels(string $language): array
    {
        // UI presets are translated here, without an additional AI request.
        // Unknown language tags use English structural labels; the summary stays
        // in the requested language.
        $translations = [
            'en' => ['title' => 'Work Report', 'project' => 'Project', 'period' => 'Period', 'summary' => 'Summary', 'entries' => 'Work entries', 'date' => 'Date', 'task' => 'Task', 'outcome' => 'Work performed / outcome', 'contributor' => 'Contributor', 'time' => 'Time', 'empty' => 'No time entries for this period.', 'missing' => 'Work on this task (outcome not specified).', 'totals' => 'Time summary', 'total' => 'Total time', 'creative' => 'Creative work', 'count' => 'Entries'],
            'pl' => ['title' => 'Raport pracy', 'project' => 'Projekt', 'period' => 'Okres', 'summary' => 'Podsumowanie', 'entries' => 'Wykonane prace', 'date' => 'Data', 'task' => 'Zadanie', 'outcome' => 'Wykonana praca / rezultat', 'contributor' => 'Osoba', 'time' => 'Czas', 'empty' => 'Brak wpisów czasu w wybranym okresie.', 'missing' => 'Brak danych', 'totals' => 'Zestawienie czasu', 'total' => 'Łączny czas', 'creative' => 'Praca twórcza', 'count' => 'Liczba wpisów'],
            'de' => ['title' => 'Arbeitsbericht', 'project' => 'Projekt', 'period' => 'Zeitraum', 'summary' => 'Zusammenfassung', 'entries' => 'Arbeitsnachweise', 'date' => 'Datum', 'task' => 'Aufgabe', 'outcome' => 'Durchgeführte Arbeit / Ergebnis', 'contributor' => 'Person', 'time' => 'Zeit', 'empty' => 'Keine Zeiteinträge in diesem Zeitraum.', 'missing' => 'Arbeit an dieser Aufgabe (Ergebnis nicht angegeben).', 'totals' => 'Zeitübersicht', 'total' => 'Gesamtzeit', 'creative' => 'Kreative Arbeit', 'count' => 'Einträge'],
            'fr' => ['title' => 'Rapport de travail', 'project' => 'Projet', 'period' => 'Période', 'summary' => 'Résumé', 'entries' => 'Travaux réalisés', 'date' => 'Date', 'task' => 'Tâche', 'outcome' => 'Travail effectué / résultat', 'contributor' => 'Personne', 'time' => 'Durée', 'empty' => 'Aucune saisie de temps pour cette période.', 'missing' => 'Travail sur cette tâche (résultat non précisé).', 'totals' => 'Résumé du temps', 'total' => 'Temps total', 'creative' => 'Travail créatif', 'count' => 'Entrées'],
            'es' => ['title' => 'Informe de trabajo', 'project' => 'Proyecto', 'period' => 'Período', 'summary' => 'Resumen', 'entries' => 'Trabajos realizados', 'date' => 'Fecha', 'task' => 'Tarea', 'outcome' => 'Trabajo realizado / resultado', 'contributor' => 'Colaborador', 'time' => 'Tiempo', 'empty' => 'No hay registros de tiempo en este período.', 'missing' => 'Trabajo en esta tarea (resultado no especificado).', 'totals' => 'Resumen de tiempo', 'total' => 'Tiempo total', 'creative' => 'Trabajo creativo', 'count' => 'Registros'],
            'it' => ['title' => 'Rapporto di lavoro', 'project' => 'Progetto', 'period' => 'Periodo', 'summary' => 'Riepilogo', 'entries' => 'Attività svolte', 'date' => 'Data', 'task' => 'Attività', 'outcome' => 'Lavoro svolto / risultato', 'contributor' => 'Collaboratore', 'time' => 'Tempo', 'empty' => 'Nessuna registrazione nel periodo.', 'missing' => 'Lavoro su questa attività (risultato non specificato).', 'totals' => 'Riepilogo tempi', 'total' => 'Tempo totale', 'creative' => 'Lavoro creativo', 'count' => 'Registrazioni'],
            'pt' => ['title' => 'Relatório de trabalho', 'project' => 'Projeto', 'period' => 'Período', 'summary' => 'Resumo', 'entries' => 'Trabalhos realizados', 'date' => 'Data', 'task' => 'Tarefa', 'outcome' => 'Trabalho realizado / resultado', 'contributor' => 'Colaborador', 'time' => 'Tempo', 'empty' => 'Sem registros de tempo neste período.', 'missing' => 'Trabalho nesta tarefa (resultado não especificado).', 'totals' => 'Resumo do tempo', 'total' => 'Tempo total', 'creative' => 'Trabalho criativo', 'count' => 'Registros'],
            'nl' => ['title' => 'Werkrapport', 'project' => 'Project', 'period' => 'Periode', 'summary' => 'Samenvatting', 'entries' => 'Uitgevoerde werkzaamheden', 'date' => 'Datum', 'task' => 'Taak', 'outcome' => 'Werk / resultaat', 'contributor' => 'Medewerker', 'time' => 'Tijd', 'empty' => 'Geen tijdregistraties in deze periode.', 'missing' => 'Werk aan deze taak (resultaat niet gespecificeerd).', 'totals' => 'Tijdoverzicht', 'total' => 'Totale tijd', 'creative' => 'Creatief werk', 'count' => 'Registraties'],
            'uk' => ['title' => 'Звіт про роботу', 'project' => 'Проєкт', 'period' => 'Період', 'summary' => 'Підсумок', 'entries' => 'Виконані роботи', 'date' => 'Дата', 'task' => 'Завдання', 'outcome' => 'Виконана робота / результат', 'contributor' => 'Учасник', 'time' => 'Час', 'empty' => 'Немає записів часу за цей період.', 'missing' => 'Робота над завданням (результат не вказано).', 'totals' => 'Підсумок часу', 'total' => 'Загальний час', 'creative' => 'Творча робота', 'count' => 'Записи'],
            'ja' => ['title' => '作業報告書', 'project' => 'プロジェクト', 'period' => '期間', 'summary' => '概要', 'entries' => '作業記録', 'date' => '日付', 'task' => 'タスク', 'outcome' => '作業内容・成果', 'contributor' => '担当者', 'time' => '時間', 'empty' => 'この期間の作業記録はありません。', 'missing' => 'このタスクの作業（成果の詳細は未記載）。', 'totals' => '作業時間の集計', 'total' => '合計時間', 'creative' => '創造的作業', 'count' => '記録数'],
            'ko' => ['title' => '작업 보고서', 'project' => '프로젝트', 'period' => '기간', 'summary' => '요약', 'entries' => '작업 기록', 'date' => '날짜', 'task' => '작업', 'outcome' => '수행 작업 / 결과', 'contributor' => '담당자', 'time' => '시간', 'empty' => '이 기간의 작업 기록이 없습니다.', 'missing' => '이 작업 수행 (결과 세부사항 없음).', 'totals' => '작업 시간 요약', 'total' => '총 작업 시간', 'creative' => '창의적 작업', 'count' => '기록 수'],
            'zh' => ['title' => '工作报告', 'project' => '项目', 'period' => '期间', 'summary' => '概述', 'entries' => '工作记录', 'date' => '日期', 'task' => '任务', 'outcome' => '工作内容 / 成果', 'contributor' => '人员', 'time' => '时间', 'empty' => '此期间没有工时记录。', 'missing' => '进行了该任务的工作（未注明成果）。', 'totals' => '工时汇总', 'total' => '总工时', 'creative' => '创意工作', 'count' => '记录数'],
        ];
        $primary = strtolower(explode('-', $language)[0]);

        return $translations[$primary] ?? $translations['en'];
    }

    private function duration(int $minutes): string
    {
        return intdiv($minutes, 60).'h '.($minutes % 60).'m';
    }

    private function cell(string $text): string
    {
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);

        return str_replace(['\\', '|', '`', '<', '>'], ['\\\\', '\\|', '\\`', '&lt;', '&gt;'], $text);
    }
}
