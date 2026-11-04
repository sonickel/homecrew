<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Attributes for is_granted()/denyAccessUnlessGranted(); every decision is made by a voter in App\Security\Voter.
 */
final class Permission
{
    /** Administration ("Verwaltung"): admin right, second factor set up, full personal login – never by PIN or team tablet. */
    public const string ADMIN = 'ADMIN';

    /** Change a person's master data and rights (subject: AppUser). */
    public const string PERSON_EDIT = 'PERSON_EDIT';

    /** Manage another person's access: start password/PIN, invitation, reset, lock, leaving (subject: AppUser). */
    public const string PERSON_ACCESS = 'PERSON_ACCESS';

    /** Team view of a paired tablet without personal login (mode "Team" or "Beides"). */
    public const string TEAM_VIEW = 'TEAM_VIEW';

    /** Log in by name and PIN at this tablet (subject: the Request – it may come from outside the home network). */
    public const string PIN_LOGIN = 'PIN_LOGIN';

    /** Change the own password, second factor, passkeys and recovery codes: only after a full login, never by PIN. */
    public const string ACCOUNT_LOGIN_SETTINGS = 'ACCOUNT_LOGIN_SETTINGS';

    /** Change the own PIN: only while logged in by PIN at the tablet. */
    public const string PIN_CHANGE = 'PIN_CHANGE';

    /** Emergency page: every person and the paired tablet (also without PIN). */
    public const string EMERGENCY_VIEW = 'EMERGENCY_VIEW';

    /** "Heute", the task list and creating one-off tasks: every person and the team tablet (konzept-1 §3). */
    public const string TASK_CREATE = 'TASK_CREATE';

    /** See a task (subject: Task) – App\Service\Visibility\VisibilityRules. */
    public const string TASK_VIEW = 'TASK_VIEW';

    /** Complete, skip or postpone an open task (subject: Task): whoever sees it. */
    public const string TASK_ACT = 'TASK_ACT';

    /** Hand an open task on (subject: Task): admins every task they see, everybody else what they are responsible for. */
    public const string TASK_HANDOVER = 'TASK_HANDOVER';

    /** Change an open task (subject: Task): admins and the person who created it. */
    public const string TASK_EDIT = 'TASK_EDIT';

    /** "Routinen & Wartung" (no subject): every person and the team tablet; a plan (subject: TaskTemplate): whoever sees it. */
    public const string PLAN_VIEW = 'PLAN_VIEW';

    /** Create plans (no subject) or change, pause, end one they see (subject: TaskTemplate): admins and persons with the switch "wiederkehrende Aufgaben". */
    public const string PLAN_MANAGE = 'PLAN_MANAGE';
}
