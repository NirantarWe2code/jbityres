<?php
/**
 * Session + MySQL + form actions (upload, navigation, year toggles).
 */

declare(strict_types=1);

session_start();

require_once __DIR__ . '/db_store.php';
require_once __DIR__ . '/auth.php';

ensure_auth_schema();
require_login_or_redirect();

function redirect_index(): void
{
    header('Location: index.php', true, 302);
    exit;
}

function clear_dashboard_data_cache(): void
{
    unset($_SESSION['year_data_cache'], $_SESSION['years_from_lines_cache']);
}

/** Default session UI state */
function session_defaults(): void
{
    if (!isset($_SESSION['hidden_years'])) {
        $_SESSION['hidden_years'] = [];
    }
    $_SESSION['view'] ??= 'overview';
    $_SESSION['cust_tab'] ??= 'all';
    $_SESSION['quick_filter'] ??= 'year';
}

try {
    $action = $_POST['action'] ?? '';

    if ($action === '') {
        redirect_index();
    }

    session_defaults();

    if ($action === 'set_view') {
        $v = (string) ($_POST['view'] ?? 'overview');
        $allowed = ['overview', 'monthly', 'brands', 'customers', 'reps', 'activity', 'rawdata'];
        $_SESSION['view'] = in_array($v, $allowed, true) ? $v : 'overview';
        redirect_index();
    }

    if ($action === 'set_quick_filter') {
        $quickFilter = (string) ($_POST['quick_filter'] ?? 'year');
        $_SESSION['quick_filter'] = in_array($quickFilter, ['1m', '3m', '6m', 'year'], true) ? $quickFilter : 'year';
        redirect_index();
    }

    if ($action === 'set_selected_years') {
        $raw = $_POST['years'] ?? [];
        if (!is_array($raw)) {
            $raw = $raw !== '' && $raw !== null ? [(string) $raw] : [];
        }
        $selected = [];
        foreach ($raw as $v) {
            $yi = (int) $v;
            if ($yi > 0) {
                $selected[] = $yi;
            }
        }
        $selected = array_values(array_unique($selected));
        sort($selected, SORT_NUMERIC);
        $validAll = list_dashboard_compare_years();
        $selected = array_values(array_filter($selected, static fn ($y) => in_array($y, $validAll, true)));
        if ($selected === []) {
            $_SESSION['hidden_years'] = [];
            $_SESSION['active_years'] = $validAll;
        } else {
            $_SESSION['hidden_years'] = array_values(array_filter($validAll, static fn ($y) => !in_array($y, $selected, true)));
            $_SESSION['active_years'] = $selected;
        }
        foreach (['cust_year', 'activity_year', 'area_year', 'brand_chart_year'] as $sk) {
            if (isset($_SESSION[$sk]) && !in_array((int) $_SESSION[$sk], $_SESSION['active_years'], true)) {
                unset($_SESSION[$sk]);
            }
        }
        clear_dashboard_data_cache();
        $_SESSION['flash_storage_msg'] = '✓ Compare years updated';
        redirect_index();
    }

    if ($action === 'toggle_year') {
        $y = (int) ($_POST['year'] ?? 0);
        $active = $_SESSION['active_years'] ?? [];
        if (in_array($y, $active, true)) {
            $active = array_values(array_filter($active, static fn ($x) => (int) $x !== $y));
        } else {
            $active[] = $y;
            sort($active, SORT_NUMERIC);
        }
        $_SESSION['active_years'] = $active;
        redirect_index();
    }

    if ($action === 'set_cust_year') {
        $_SESSION['cust_year'] = (int) ($_POST['year'] ?? 0);
        $_SESSION['cust_tab'] = 'all';
        redirect_index();
    }

    if ($action === 'set_cust_tab') {
        $_SESSION['cust_tab'] = (string) ($_POST['tab'] ?? 'all');
        redirect_index();
    }

    if ($action === 'set_activity_year') {
        $_SESSION['activity_year'] = (int) ($_POST['year'] ?? 0);
        redirect_index();
    }

    if ($action === 'set_area_year') {
        $_SESSION['area_year'] = (int) ($_POST['year'] ?? 0);
        redirect_index();
    }

    if ($action === 'set_brand_chart_year') {
        $_SESSION['brand_chart_year'] = (int) ($_POST['year'] ?? 0);
        redirect_index();
    }

    if ($action === 'remove_year') {
        $y = (int) ($_POST['year'] ?? 0);
        $active = array_values(array_filter($_SESSION['active_years'] ?? [], static fn ($x) => (int) $x !== $y));
        $_SESSION['active_years'] = $active;
        $hidden = array_values(array_unique(array_map('intval', $_SESSION['hidden_years'] ?? [])));
        if (!in_array($y, $hidden, true)) {
            $hidden[] = $y;
            sort($hidden, SORT_NUMERIC);
        }
        $_SESSION['hidden_years'] = $hidden;
        if (($tmp = $_SESSION['cust_year'] ?? null) === $y) {
            unset($_SESSION['cust_year']);
        }
        if (($tmp = $_SESSION['activity_year'] ?? null) === $y) {
            unset($_SESSION['activity_year']);
        }
        if (($tmp = $_SESSION['area_year'] ?? null) === $y) {
            unset($_SESSION['area_year']);
        }
        if (($tmp = $_SESSION['brand_chart_year'] ?? null) === $y) {
            unset($_SESSION['brand_chart_year']);
        }
        $_SESSION['flash_storage_msg'] = '✓ ' . $y . ' removed from view';
        redirect_index();
    }


    redirect_index();
} catch (Throwable $e) {
    $_SESSION['flash_error'] = 'Database error: ' . $e->getMessage();
    header('Location: index.php', true, 302);
    exit;
}
