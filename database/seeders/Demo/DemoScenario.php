<?php

namespace Database\Seeders\Demo;

use App\Enums\TaskPriority;
use App\Enums\TaskType;

/**
 * Stable, human-readable sample data. Identifiers are per-project task numbers.
 * Days refer to the demo workspace's creation date, not the time of each seeding run.
 */
final class DemoScenario
{
    /** @return array<int, array<string, mixed>> */
    public static function applicationTasks(): array
    {
        return [
            1 => ['day' => 5, 'title' => 'Guide new teams through workspace setup', 'description' => 'Introduce an onboarding checklist explaining how to create a workspace, invite teammates, and choose a project. Show progress after each completed step.', 'type' => TaskType::FEATURE, 'priority' => TaskPriority::HIGH, 'status' => 'done', 'assignee' => 'developer', 'release' => '1.0.0'],
            2 => ['day' => 7, 'title' => 'Invite teammates with workspace roles', 'description' => 'Allow owners to invite members as admins or contributors. Verify that ordinary members cannot change workspace permissions.', 'type' => TaskType::FEATURE, 'priority' => TaskPriority::HIGH, 'status' => 'done', 'assignee' => 'owner', 'release' => '1.0.0'],
            3 => ['day' => 9, 'title' => 'Build the project overview dashboard', 'description' => 'Show active tasks, assignees, upcoming deadlines, and current project progress. The empty state should explain how to add the first task.', 'type' => TaskType::FEATURE, 'priority' => TaskPriority::NORMAL, 'status' => 'done', 'assignee' => 'developer', 'release' => '1.0.0'],
            4 => ['day' => 12, 'title' => 'Filter tasks by priority and assignee', 'description' => 'Make it possible to combine task status, priority, assignee, and text search without losing the chosen filters while navigating.', 'type' => TaskType::IMPROVEMENT, 'priority' => TaskPriority::NORMAL, 'status' => 'done', 'assignee' => 'developer', 'release' => '1.0.0'],
            5 => ['day' => 15, 'title' => 'Improve validation messages in task forms', 'description' => 'Show readable field-level errors and preserve user input after failed submissions. Validate project membership before assigning people.', 'type' => TaskType::IMPROVEMENT, 'priority' => TaskPriority::NORMAL, 'status' => 'done', 'assignee' => 'developer', 'release' => '1.0.0'],
            6 => ['day' => 18, 'title' => 'Fix due-date timezone interpretation', 'description' => 'Dates selected as calendar dates must not shift by a day when viewed in a different timezone. Add regression tests for date-only fields.', 'type' => TaskType::BUG, 'priority' => TaskPriority::URGENT, 'status' => 'done', 'assignee' => 'developer', 'release' => '1.0.0'],
            7 => ['day' => 34, 'title' => 'Notify teammates about mentions in discussions', 'description' => 'When a participant is mentioned in a task discussion, show an in-app notification. Respect notification preferences and prevent duplicate notifications.', 'type' => TaskType::FEATURE, 'priority' => TaskPriority::HIGH, 'status' => 'in-progress', 'assignee' => 'developer', 'release' => '1.1.0'],
            8 => ['day' => 36, 'title' => 'Export project reports as CSV', 'description' => 'Allow project managers to export completed tasks within a selected date range. Include title, status, assignee, completion date, and effort summary.', 'type' => TaskType::FEATURE, 'priority' => TaskPriority::HIGH, 'status' => 'in-progress', 'assignee' => 'owner', 'release' => '1.1.0'],
            9 => ['day' => 39, 'title' => 'Show task activity as a readable timeline', 'description' => 'Group field changes by date, show who made each change, and keep user discussion separate from technical task activity.', 'type' => TaskType::IMPROVEMENT, 'priority' => TaskPriority::NORMAL, 'status' => 'done', 'assignee' => 'developer', 'release' => '1.1.0'],
            10 => ['day' => 43, 'title' => 'Speed up task list filtering', 'description' => 'Reduce unnecessary queries on the Tasks page. Keep pagination, search and ordering stable after applying or clearing filters.', 'type' => TaskType::IMPROVEMENT, 'priority' => TaskPriority::HIGH, 'status' => 'in-progress', 'assignee' => 'developer', 'release' => '1.1.0'],
            11 => ['day' => 46, 'title' => 'Allow reactions in task discussions', 'description' => 'Support lightweight reactions to feedback without creating a separate message. Ensure access control matches the discussion permissions.', 'type' => TaskType::FEATURE, 'priority' => TaskPriority::LOW, 'status' => 'todo', 'assignee' => null, 'release' => '1.1.0'],
            12 => ['day' => 49, 'title' => 'Add passkey sign-in to account settings', 'description' => 'Allow registered users to add and remove passkeys securely. Keep password sign-in available and test passkey failures.', 'type' => TaskType::FEATURE, 'priority' => TaskPriority::NORMAL, 'status' => 'done', 'assignee' => 'owner', 'release' => '1.1.0'],
            13 => ['day' => 56, 'title' => 'Add a workspace security audit view', 'description' => 'Display sensitive membership and permission changes in a searchable audit view for workspace owners.', 'type' => TaskType::FEATURE, 'priority' => TaskPriority::HIGH, 'status' => 'todo', 'assignee' => 'owner', 'release' => '1.2.0'],
            14 => ['day' => 58, 'title' => 'Let users configure notification preferences', 'description' => 'Allow individual email and in-app notification preferences for task assignments, mentions and project updates.', 'type' => TaskType::FEATURE, 'priority' => TaskPriority::NORMAL, 'status' => 'todo', 'assignee' => 'developer', 'release' => '1.2.0'],
            15 => ['day' => 52, 'title' => 'Review accessibility of project forms', 'description' => 'Audit keyboard navigation, labels, focus order and error announcements on project and task forms.', 'type' => TaskType::IMPROVEMENT, 'priority' => TaskPriority::NORMAL, 'status' => 'in-progress', 'assignee' => 'reviewer', 'release' => null],
            16 => ['day' => 54, 'title' => 'Fix release page layout on narrow screens', 'description' => 'Action buttons and form controls should remain aligned and accessible at mobile breakpoints.', 'type' => TaskType::BUG, 'priority' => TaskPriority::HIGH, 'status' => 'done', 'assignee' => 'developer', 'release' => null],
            17 => ['day' => 55, 'title' => 'Export completed work as a PDF summary', 'description' => 'Investigate a readable report for project stakeholders, including selected tasks and notable outcomes.', 'type' => TaskType::TASK, 'priority' => TaskPriority::LOW, 'status' => 'todo', 'assignee' => null, 'release' => null],
            18 => ['day' => 57, 'title' => 'Add a mention-notification preview', 'description' => 'Follow-up from the mentions discussion: let users preview the notification message before it is delivered. This is an independent task, not a subtask.', 'type' => TaskType::TASK, 'priority' => TaskPriority::NORMAL, 'status' => 'in-progress', 'assignee' => 'developer', 'release' => null],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public static function websiteTasks(): array
    {
        return [
            1 => ['day' => 14, 'title' => 'Refresh the homepage hero section', 'description' => 'Update the main headline, call to action and supporting image for the autumn campaign.', 'type' => TaskType::IMPROVEMENT, 'priority' => TaskPriority::NORMAL, 'status' => 'done', 'assignee' => 'developer'],
            2 => ['day' => 17, 'title' => 'Fix mobile navigation overflow', 'description' => 'Prevent the main menu from overlapping the page content on smaller devices.', 'type' => TaskType::BUG, 'priority' => TaskPriority::HIGH, 'status' => 'done', 'assignee' => 'developer'],
            3 => ['day' => 23, 'title' => 'Optimize editorial image loading', 'description' => 'Introduce appropriate image sizes and lazy loading on article lists.', 'type' => TaskType::IMPROVEMENT, 'priority' => TaskPriority::NORMAL, 'status' => 'in-progress', 'assignee' => 'developer'],
            4 => ['day' => 32, 'title' => 'Investigate contact form delivery issue', 'description' => 'Some messages are not delivered when the mail server times out. Improve error reporting and retries.', 'type' => TaskType::BUG, 'priority' => TaskPriority::URGENT, 'status' => 'todo', 'assignee' => 'owner'],
            5 => ['day' => 40, 'title' => 'Prepare an editorial calendar', 'description' => 'Create a simple content plan for the next six weeks, including publication dates and owners.', 'type' => TaskType::TASK, 'priority' => TaskPriority::LOW, 'status' => 'in-progress', 'assignee' => 'reviewer'],
        ];
    }

    /**
     * Entries: [task number, day, minute-of-day, author key, message].
     * Multilingual material is intentional for testing AI-generated release notes.
     *
     * @return array<int, array{int,int,int,string,string}>
     */
    public static function messages(): array
    {
        return [
            [1, 7, 610, 'owner', 'Let us make onboarding usable without a walkthrough. Owners should finish the setup in under two minutes.'],
            [1, 10, 580, 'reviewer', 'Przetestowałam ścieżkę od początku. Warto wyraźniej pokazać wybór pierwszego projektu.'],
            [2, 9, 560, 'developer', 'Invitations now verify roles on the server; a workspace member cannot promote themselves.'],
            [3, 12, 700, 'reviewer', 'The empty dashboard should say what to do next, not just show an empty card.'],
            [4, 15, 600, 'owner', 'Keep status and assignee filters when coming back from a task.'],
            [5, 19, 740, 'reviewer', 'Komunikat przy przypisaniu osoby spoza projektu powinien wskazywać konkretny problem.'],
            [6, 20, 570, 'developer', 'Reproduced: a date-only deadline moves back a day when parsed with UTC conversion.'],
            [6, 22, 650, 'reviewer', 'The regression test now covers Europe/Warsaw and UTC.'],
            [7, 36, 570, 'owner', 'We need notifications for mentions, but only once per person and message.'],
            [7, 38, 650, 'developer', 'Implementacja jest w toku. W pierwszej wersji powiadomienie będzie widoczne w aplikacji.'],
            [7, 40, 615, 'reviewer', 'Czy możemy dodać podgląd treści powiadomienia przed wysłaniem? To powinno być osobne zadanie, nie podzadanie.'],
            [7, 42, 710, 'owner', 'Yes. Create a separate task from that message; do not block delivery of the current feature.'],
            [8, 38, 610, 'reviewer', 'Raport CSV powinien zawierać datę ukończenia oraz osobę przypisaną do zadania.'],
            [8, 41, 720, 'developer', 'Please keep the export independent of the browser locale for numeric and date fields.'],
            [9, 42, 570, 'owner', 'Important: Activity and Discussion are different sources and should not be merged in the task view.'],
            [9, 45, 600, 'reviewer', 'Widok historii jest czytelny. Szczególnie przydatne są informacje, kto zmienił status.'],
            [10, 46, 590, 'developer', 'Indexing status and assignee reduced the worst-case task list query count.'],
            [10, 48, 640, 'owner', 'Please test pagination when clearing filters — it should go back to the first page.'],
            [11, 48, 630, 'reviewer', 'Would a simple thumbs-up reaction be enough for the first iteration?'],
            [12, 51, 600, 'developer', 'Passkeys should remain optional; password login is still required as fallback.'],
            [12, 54, 560, 'reviewer', 'Testy logowania kluczem dostępu przeszły pomyślnie.'],
            [13, 58, 600, 'owner', 'We need an owner-only log of permission changes before inviting external partners.'],
            [14, 60, 570, 'developer', 'Preferences will be per-user, not global to the workspace.'],
            [15, 54, 600, 'reviewer', 'There are still missing keyboard focus indicators on the project forms.'],
            [16, 56, 660, 'reviewer', 'The release form no longer overflows on a 375px viewport.'],
            [18, 60, 740, 'developer', 'The preview can reuse the same notification payload without sending anything.'],
        ];
    }
}
