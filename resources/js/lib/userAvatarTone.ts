// Identity palette for people: clear, colorful initials on soft circles.
// Project icons intentionally use a different shape and stronger saturation.
const userTones = [
    'bg-[#dbeafe] text-[#1d4ed8] dark:bg-[#1e3a5f] dark:text-[#bfdbfe]',
    'bg-[#ccfbf1] text-[#0f766e] dark:bg-[#134e4a] dark:text-[#99f6e4]',
    'bg-[#ede9fe] text-[#6d28d9] dark:bg-[#3b2865] dark:text-[#ddd6fe]',
    'bg-[#ffedd5] text-[#c2410c] dark:bg-[#57301f] dark:text-[#fed7aa]',
    'bg-[#ffe4e6] text-[#be123c] dark:bg-[#5a2032] dark:text-[#fecdd3]',
    'bg-[#cffafe] text-[#0e7490] dark:bg-[#164e63] dark:text-[#a5f3fc]',
    'bg-[#fef3c7] text-[#92400e] dark:bg-[#554019] dark:text-[#fde68a]',
    'bg-[#e0f2fe] text-[#0369a1] dark:bg-[#173f5a] dark:text-[#bae6fd]',
    'bg-[#e0e7ff] text-[#3730a3] dark:bg-[#312e59] dark:text-[#c7d2fe]',
    'bg-[#d1fae5] text-[#065f46] dark:bg-[#12493b] dark:text-[#a7f3d0]',
    'bg-[#ecfccb] text-[#365314] dark:bg-[#35451c] dark:text-[#d9f99d]',
    'bg-[#fae8ff] text-[#86198f] dark:bg-[#512354] dark:text-[#f5d0fe]',
    'bg-[#fce7f3] text-[#9d174d] dark:bg-[#53263e] dark:text-[#fbcfe8]',
    'bg-[#fee2e2] text-[#991b1b] dark:bg-[#582525] dark:text-[#fecaca]',
    'bg-[#e7e5e4] text-[#44403c] dark:bg-[#44403c] dark:text-[#e7e5e4]',
    'bg-[#e2e8f0] text-[#334155] dark:bg-[#334155] dark:text-[#e2e8f0]',
] as const;

export function userAvatarTone(userId: number): string {
    return userTones[Math.abs(userId) % userTones.length] ?? userTones[0];
}
