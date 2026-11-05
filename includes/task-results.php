<div class="task-results" id="task-results" data-task-results>
    <?php if ($databaseError === '' && $tasks === []): ?>
        <div class="task-empty-state surface">
            <?php if ($overview === null || $overview['total'] === 0): ?>
                <span class="project-empty-icon" aria-hidden="true">✓</span>
            <?php endif; ?>
            <h3><?= $overview !== null && $overview['total'] > 0
                ? 'No tasks match these filters'
                : 'No tasks yet' ?></h3>
            <p><?= $overview !== null && $overview['total'] > 0
                ? 'Try another status, build, or priority.'
                : 'Add a task above to keep your next build steps organized.' ?></p>
            <?php if ($filters['status'] !== 'all'
                || $filters['project_id'] !== null
                || $filters['priority'] !== 'all'): ?>
                <a
                    class="btn btn-secondary"
                    href="/bricklab/tasks.php"
                    data-task-filter-clear
                >
                    Clear filters
                </a>
            <?php endif; ?>
        </div>
    <?php elseif ($databaseError === ''): ?>
        <div class="task-list">
            <?php foreach ($tasks as $task): ?>
                <?php
                $taskStatus = (string) $task['status'];
                $taskStatusClass = $taskStatus === 'todo' ? 'planning' : $taskStatus;
                $taskPriority = (string) $task['priority'];
                $dueTimestamp = $task['due_date'] === null
                    ? false
                    : strtotime((string) $task['due_date']);
                $isOverdue = $dueTimestamp !== false
                    && $taskStatus !== 'completed'
                    && date('Y-m-d', $dueTimestamp) < date('Y-m-d');
                ?>
                <article class="task-card surface">
                    <div class="task-card-main">
                        <div class="task-card-heading">
                            <h3><?= escape_html((string) $task['title']) ?></h3>
                        </div>

                        <?php if ($task['description'] !== null && $task['description'] !== ''): ?>
                            <p class="task-description">
                                <?= nl2br(escape_html((string) $task['description'])) ?>
                            </p>
                        <?php endif; ?>

                        <div class="task-meta">
                            <a href="/bricklab/projects.php?edit=<?= (int) $task['project_id'] ?>">
                                <?= escape_html((string) $task['project_title']) ?>
                            </a>
                            <span class="task-priority task-priority-<?= escape_html($taskPriority) ?>">
                                <?= escape_html($priorityOptions[$taskPriority] ?? 'Priority unavailable') ?>
                                priority
                            </span>
                            <?php if ($dueTimestamp === false): ?>
                                <span>No due date</span>
                            <?php else: ?>
                                <span class="<?= $isOverdue ? 'task-overdue' : '' ?>">
                                    <?= $isOverdue ? 'Overdue · ' : 'Due ' ?>
                                    <?= escape_html(date('M j, Y', $dueTimestamp)) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="task-card-actions">
                        <span class="task-status-badge status-<?= escape_html($taskStatusClass) ?>">
                            <?= escape_html($statusOptions[$taskStatus] ?? 'Status unavailable') ?>
                        </span>
                        <div class="task-card-links">
                            <a
                                class="btn btn-secondary"
                                href="/bricklab/tasks.php?edit=<?= (int) $task['id'] ?>"
                            >
                                Edit
                            </a>
                            <a
                                class="btn btn-danger-outline"
                                href="/bricklab/tasks.php?<?= escape_html(http_build_query([
                                    'confirm_delete' => (int) $task['id'],
                                    'status' => $filters['status'],
                                    'project_id' => $filters['project_id'] ?? '',
                                    'priority' => $filters['priority'],
                                ])) ?>"
                            >
                                Delete
                            </a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
