import {
    BookOpen, Building2, Code2, Database, FolderKanban, Globe,
    Package, Rocket, Server, ShoppingBag, Smartphone, Wrench,
    Monitor, Terminal, Cloud, GitBranch, Workflow, Cpu, ShieldCheck,
    ChartNoAxesCombined, CalendarDays, MessageSquare, Users, BriefcaseBusiness,
    GraduationCap, Palette, Camera, Mail, Bot, Heart,
} from '@lucide/vue';
import type { Component } from 'vue';
import type { ProjectColor, ProjectIcon, ProjectType } from '@/types/project';

export const projectIcons: Record<ProjectIcon, Component> = {
    'folder-kanban': FolderKanban,
    'code-2': Code2,
    'shopping-bag': ShoppingBag,
    'building-2': Building2,
    package: Package,
    globe: Globe,
    server: Server,
    database: Database,
    rocket: Rocket,
    'book-open': BookOpen,
    smartphone: Smartphone,
    wrench: Wrench,
    'monitor': Monitor,
    'terminal': Terminal,
    'cloud': Cloud,
    'git-branch': GitBranch,
    'workflow': Workflow,
    'cpu': Cpu,
    'shield-check': ShieldCheck,
    'chart-no-axes-combined': ChartNoAxesCombined,
    'calendar-days': CalendarDays,
    'message-square': MessageSquare,
    'users': Users,
    'briefcase-business': BriefcaseBusiness,
    'graduation-cap': GraduationCap,
    'palette': Palette,
    'camera': Camera,
    'mail': Mail,
    'bot': Bot,
    'heart': Heart,
};

export const projectIconOptions = Object.keys(projectIcons) as ProjectIcon[];

// Explicit Tailwind classes and matching accent values shared by project identities and the sidebar.
export const projectColors: Record<ProjectColor, { background: string; foreground: string; dot: string; accent: string }> = {
    slate:   { background: 'bg-slate-500/10 dark:bg-slate-400/15',   foreground: 'text-slate-700 dark:text-slate-300',   dot: 'bg-slate-500', accent: '#64748b' },
    blue:    { background: 'bg-blue-500/10 dark:bg-blue-400/15',     foreground: 'text-blue-700 dark:text-blue-300',     dot: 'bg-blue-500', accent: '#3b82f6' },
    emerald: { background: 'bg-emerald-500/10 dark:bg-emerald-400/15', foreground: 'text-emerald-700 dark:text-emerald-300', dot: 'bg-emerald-500', accent: '#10b981' },
    violet:  { background: 'bg-violet-500/10 dark:bg-violet-400/15', foreground: 'text-violet-700 dark:text-violet-300', dot: 'bg-violet-500', accent: '#8b5cf6' },
    amber:   { background: 'bg-amber-500/10 dark:bg-amber-400/15', foreground: 'text-amber-700 dark:text-amber-300', dot: 'bg-amber-500', accent: '#f59e0b' },
    rose:    { background: 'bg-rose-500/10 dark:bg-rose-400/15', foreground: 'text-rose-700 dark:text-rose-300', dot: 'bg-rose-500', accent: '#f43f5e' },
    cyan:    { background: 'bg-cyan-500/10 dark:bg-cyan-400/15', foreground: 'text-cyan-700 dark:text-cyan-300', dot: 'bg-cyan-500', accent: '#06b6d4' },
    orange:  { background: 'bg-orange-500/10 dark:bg-orange-400/15', foreground: 'text-orange-700 dark:text-orange-300', dot: 'bg-orange-500', accent: '#f97316' },
    indigo:  { background: 'bg-indigo-500/10 dark:bg-indigo-400/15', foreground: 'text-indigo-700 dark:text-indigo-300', dot: 'bg-indigo-500', accent: '#6366f1' },
    teal:    { background: 'bg-teal-500/10 dark:bg-teal-400/15', foreground: 'text-teal-700 dark:text-teal-300', dot: 'bg-teal-500', accent: '#14b8a6' },
    lime:    { background: 'bg-lime-500/10 dark:bg-lime-400/15', foreground: 'text-lime-800 dark:text-lime-300', dot: 'bg-lime-500', accent: '#84cc16' },
    fuchsia: { background: 'bg-fuchsia-500/10 dark:bg-fuchsia-400/15', foreground: 'text-fuchsia-700 dark:text-fuchsia-300', dot: 'bg-fuchsia-500', accent: '#d946ef' },
    pink:    { background: 'bg-pink-500/10 dark:bg-pink-400/15', foreground: 'text-pink-700 dark:text-pink-300', dot: 'bg-pink-500', accent: '#ec4899' },
    red:     { background: 'bg-red-500/10 dark:bg-red-400/15', foreground: 'text-red-700 dark:text-red-300', dot: 'bg-red-500', accent: '#ef4444' },
    sky:     { background: 'bg-sky-500/10 dark:bg-sky-400/15', foreground: 'text-sky-700 dark:text-sky-300', dot: 'bg-sky-500', accent: '#0ea5e9' },
    stone:   { background: 'bg-stone-500/10 dark:bg-stone-400/15', foreground: 'text-stone-700 dark:text-stone-300', dot: 'bg-stone-500', accent: '#78716c' },
};
export const projectColorOptions = Object.keys(projectColors) as ProjectColor[];

export function projectIconFor(icon: ProjectIcon | null | undefined, type: ProjectType | undefined): Component {
    return projectIcons[icon ?? (type === 'application' ? 'code-2' : 'folder-kanban')];
}
