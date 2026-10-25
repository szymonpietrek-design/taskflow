import test from 'node:test';
import assert from 'node:assert/strict';
import { canIncludeVcsSource, normalizeReportSources, toggleReportSource } from '../../resources/js/lib/reportSourceSelection.ts';

test('VCS requires team project summary and all contributors', () => {
    assert.equal(canIncludeVcsSource('project_summary', 'all', true), true);
    assert.equal(canIncludeVcsSource('technical_report', 'all', true), true);
    assert.equal(canIncludeVcsSource('work_report', 'all', true), false);
    assert.equal(canIncludeVcsSource('project_summary', 'selected', true), false);
    assert.equal(canIncludeVcsSource('technical_report', 'selected', true), false);
    assert.equal(canIncludeVcsSource('project_summary', 'all', false), false);
});

test('VCS cannot be selected when no repository is connected', () => {
    assert.equal(canIncludeVcsSource('project_summary', 'all', true, false), false);
    assert.deepEqual(normalizeReportSources(['vcs', 'tasks'], 'project_summary', 'all', true, false), ['tasks']);
});

test('changing report format removes ineligible VCS and requires worklogs', () => {
    assert.deepEqual(
        normalizeReportSources(['tasks', 'vcs'], 'work_report', 'all', true),
        ['tasks', 'worklogs'],
    );
});

test('switching to selected people removes VCS without removing other sources', () => {
    assert.deepEqual(
        normalizeReportSources(['messages', 'vcs', 'messages'], 'project_summary', 'selected', true),
        ['messages'],
    );
});

test('required worklogs cannot be deselected in a work report', () => {
    assert.deepEqual(
        toggleReportSource(['worklogs'], 'worklogs', false, 'work_report', 'all', true),
        ['worklogs'],
    );
});

test('disabled VCS cannot be enabled programmatically', () => {
    assert.deepEqual(
        toggleReportSource(['tasks'], 'vcs', true, 'project_summary', 'selected', true),
        ['tasks'],
    );
});

test('technical and project reports retain the same VCS sources when switching between formats', () => {
    assert.deepEqual(
        normalizeReportSources(['tasks', 'vcs'], 'technical_report', 'all', true),
        ['tasks', 'vcs'],
    );
    assert.deepEqual(
        normalizeReportSources(['tasks', 'vcs'], 'project_summary', 'all', true),
        ['tasks', 'vcs'],
    );
});

test('switching back to team summary permits explicitly selecting VCS', () => {
    assert.deepEqual(
        toggleReportSource(['tasks'], 'vcs', true, 'project_summary', 'all', true),
        ['tasks', 'vcs'],
    );
});
