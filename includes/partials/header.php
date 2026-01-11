<!-- Page Header -->
<header class="header">
    <div class="header-left">
        <button class="menu-toggle">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 12h18M3 6h18M3 18h18"/>
            </svg>
        </button>
        <h1 class="page-title"><?php echo e($pageTitle ?? 'Dashboard'); ?></h1>
    </div>

    <div class="header-right">
        <button class="header-btn" data-tooltip="Search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"/>
                <path d="M21 21l-4.35-4.35"/>
            </svg>
        </button>

        <button class="header-btn" data-tooltip="Notifications">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0"/>
            </svg>
            <span class="notification-badge"></span>
        </button>

        <button class="header-btn" data-tooltip="Help">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3M12 17h.01"/>
            </svg>
        </button>
    </div>
</header>
