@extends('backEnd.layouts.master')
@section('title',$order_status->name.' Order')
@section('css')
<style>
    .orders-page-shell {
        display: flex;
        flex-direction: column;
        gap: 22px;
    }
    .orders-hero {
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        border: 1px solid #e4ecf7;
        border-radius: 22px;
        box-shadow: 0 18px 45px rgba(15, 23, 42, 0.06);
        padding: 20px;
    }
    .orders-hero-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 18px;
        flex-wrap: wrap;
    }
    .orders-hero-main {
        display: flex;
        align-items: center;
        gap: 20px;
        flex: 1 1 auto;
        min-width: 0;
        flex-wrap: wrap;
    }
    .orders-hero-title {
        font-size: 24px;
        line-height: 1.1;
        font-weight: 800;
        color: #0f172a;
        margin: 0;
    }
    .orders-hero-breadcrumb {
        margin-top: 6px;
        font-size: 13px;
        color: #64748b;
    }
    .orders-hero-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: nowrap;
        justify-content: flex-end;
        margin-left: auto;
    }
    .orders-hero-toolbar {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        flex: 1 1 auto;
        min-width: 0;
    }
    .orders-hero-toolbar .action2-btn {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    .orders-hero-toolbar .action2-btn li {
        display: flex;
        margin: 0;
    }
    .orders-hero-toolbar .action2-btn .btn,
    .orders-top-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 12px 18px;
        min-height: 46px;
        border-radius: 12px;
        border: 1px solid #dbe6f3;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
        white-space: nowrap;
        box-shadow: 0 10px 25px rgba(15, 23, 42, 0.04);
    }
    .orders-hero-toolbar .action2-btn .btn {
        background: #ffffff;
        border-color: #dbe6f3;
        color: #334155;
    }
    .orders-hero-toolbar .action2-btn .btn:hover,
    .orders-hero-toolbar .action2-btn .btn:focus,
    .orders-top-btn:hover,
    .orders-top-btn:focus {
        background: #f8fbff;
        border-color: #cbdcf1;
        color: #0f172a;
    }
    .orders-hero-toolbar .action2-btn .btn.btn-success,
    .orders-hero-toolbar .action2-btn .btn.btn-primary,
    .orders-hero-toolbar .action2-btn .btn.btn-info,
    .orders-hero-toolbar .action2-btn .btn.btn-dark,
    .orders-hero-toolbar .action2-btn .btn.btn-danger,
    .orders-hero-toolbar .action2-btn .btn.btn-warning {
        background: #ffffff;
        border-color: #dbe6f3;
        color: #334155;
    }
    .orders-top-btn {
        background: #ffffff;
        color: #334155;
    }
    .orders-courier-dropdown .dropdown-toggle::after {
        margin-left: 8px;
    }
    .orders-courier-menu {
        min-width: 200px;
        border: 1px solid #dbe6f3;
        border-radius: 14px;
        padding: 8px;
        box-shadow: 0 16px 30px rgba(15, 23, 42, 0.12);
    }
    .orders-filter-menu {
        min-width: 220px;
        border: 1px solid #dbe6f3;
        border-radius: 14px;
        padding: 8px;
        box-shadow: 0 16px 30px rgba(15, 23, 42, 0.12);
    }
    .orders-courier-item {
        display: flex;
        align-items: center;
        gap: 10px;
        width: 100%;
        padding: 10px 12px;
        border-radius: 10px;
        color: #334155;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
    }
    .orders-courier-item:hover {
        background: #f8fbff;
        color: #0f172a;
    }
    .orders-filter-item {
        display: flex;
        align-items: center;
        gap: 10px;
        width: 100%;
        padding: 10px 12px;
        border-radius: 10px;
        color: #334155;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
    }
    .orders-filter-item:hover {
        background: #f8fbff;
        color: #0f172a;
    }
    .orders-top-btn-primary {
        background: linear-gradient(135deg, #4f46e5, #4338ca);
        border-color: transparent;
        color: #ffffff;
    }
    .orders-stats-grid {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 14px;
        margin-bottom: 18px;
    }
    .orders-stat-card {
        position: relative;
        overflow: hidden;
        border-radius: 18px;
        border: 1px solid #e5edf8;
        background: #ffffff;
        padding: 18px 16px;
        min-height: 128px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
        text-decoration: none;
    }
    .orders-stat-card:hover {
        transform: translateY(-1px);
        box-shadow: 0 14px 34px rgba(15, 23, 42, 0.08);
    }
    .orders-stat-card.active {
        border-color: #c7d7fe;
        box-shadow: 0 16px 36px rgba(79, 70, 229, 0.15);
    }
    .orders-stat-icon {
        width: 42px;
        height: 42px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        margin-bottom: 18px;
    }
    .orders-stat-value {
        font-size: 31px;
        line-height: 1;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 10px;
    }
    .orders-stat-label {
        font-size: 14px;
        color: #475569;
        font-weight: 700;
    }
    .orders-stat-note {
        margin-top: 8px;
        font-size: 12px;
        color: #16a34a;
        font-weight: 700;
    }
    .orders-stat-primary .orders-stat-icon { background: #eef2ff; color: #4f46e5; }
    .orders-stat-warning .orders-stat-icon { background: #fff7ed; color: #f59e0b; }
    .orders-stat-info .orders-stat-icon { background: #eff6ff; color: #2563eb; }
    .orders-stat-accent .orders-stat-icon { background: #f3e8ff; color: #9333ea; }
    .orders-stat-success .orders-stat-icon { background: #ecfdf5; color: #16a34a; }
    .orders-stat-danger .orders-stat-icon { background: #fef2f2; color: #ef4444; }
    .orders-toolbar {
        border: 1px solid #e4ecf7;
        border-radius: 18px;
        background: #ffffff;
        padding: 10px 14px;
    }
    .orders-toolbar-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 8px;
    }
    .orders-status-shortcuts {
        display: flex;
        flex-wrap: nowrap;
        gap: 8px;
        overflow-x: auto;
        overflow-y: hidden;
        scrollbar-width: thin;
        padding-bottom: 0;
    }
    .orders-status-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 10px;
        border-radius: 999px;
        border: 1px solid #dbe6f3;
        background: #f8fbff;
        color: #475569;
        font-size: 12px;
        font-weight: 700;
        text-decoration: none;
        white-space: nowrap;
        flex: 0 0 auto;
    }
    .orders-status-pill-text {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 2px;
        line-height: 1.05;
    }
    .orders-status-pill-percent {
        font-size: 10px;
        font-weight: 700;
        color: #64748b;
    }
    .orders-status-pill:hover,
    .orders-status-pill.active {
        background: #4f46e5;
        border-color: #4f46e5;
        color: #ffffff;
    }
    .orders-status-pill-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 20px;
        height: 20px;
        padding: 0 6px;
        border-radius: 999px;
        background: rgba(15, 23, 42, 0.08);
        font-size: 11px;
    }
    .orders-status-pill.active .orders-status-pill-count,
    .orders-status-pill:hover .orders-status-pill-count {
        background: rgba(255, 255, 255, 0.18);
    }
    .orders-status-pill.active .orders-status-pill-percent,
    .orders-status-pill:hover .orders-status-pill-percent {
        color: #e0e7ff;
    }
    .orders-toolbar-bottom {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }
    .orders-action-row {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    .orders-search-form {
        flex: 0 1 380px;
        min-width: 280px;
        margin-bottom: 0;
    }
    .orders-search-shell {
        display: flex;
        align-items: center;
        gap: 12px;
        min-height: 48px;
        padding: 0 14px;
        border-radius: 14px;
        border: 1px solid #dbe6f3;
        background: #ffffff;
        box-shadow: 0 10px 25px rgba(15, 23, 42, 0.04);
    }
    .orders-search-icon {
        color: #94a3b8;
        font-size: 16px;
        line-height: 1;
    }
    .orders-search-input {
        height: 46px;
        border: 0;
        box-shadow: none;
        padding: 0;
        color: #0f172a;
        background: transparent;
    }
    .orders-search-input:focus {
        box-shadow: none;
        background: transparent;
    }
    .orders-search-hint {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 44px;
        height: 26px;
        padding: 0 10px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        color: #94a3b8;
        font-size: 11px;
        font-weight: 700;
        white-space: nowrap;
    }
    .orders-filter-chip {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 11px 14px;
        border-radius: 12px;
        border: 1px solid #dbe6f3;
        background: #ffffff;
        color: #475569;
        font-size: 13px;
        font-weight: 700;
    }
    .orders-filter-chip.active {
        background: #eef2ff;
        border-color: #c7d2fe;
        color: #4338ca;
    }
    .orders-filter-panel {
        display: none;
        margin-top: 14px;
        padding: 16px;
        border: 1px solid #e4ecf7;
        border-radius: 18px;
        background: #f8fbff;
    }
    .orders-filter-panel.is-open {
        display: block;
    }
    .orders-filter-form {
        display: flex;
        flex-direction: column;
        gap: 14px;
    }
    .orders-filter-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }
    .orders-filter-group {
        display: none;
        padding: 14px;
        border: 1px solid #dbe6f3;
        border-radius: 14px;
        background: #ffffff;
    }
    .orders-filter-group.is-visible {
        display: block;
    }
    .orders-filter-group-title {
        margin-bottom: 10px;
        font-size: 13px;
        font-weight: 800;
        color: #334155;
    }
    .orders-filter-inline {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }
    .orders-filter-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    .orders-filter-empty {
        display: none;
        padding: 14px;
        border-radius: 14px;
        background: #ffffff;
        border: 1px dashed #cbd5e1;
        color: #64748b;
        font-size: 13px;
        font-weight: 600;
    }
    .orders-filter-empty.is-visible {
        display: block;
    }
    .orders-board {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 18px;
        overflow: hidden;
    }
    .orders-table-topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 0 0 8px;
        flex-wrap: wrap;
    }
    .orders-per-page-form {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 6px 10px;
        border: 1px solid #dbe6f3;
        border-radius: 12px;
        background: #ffffff;
    }
    .order_page .card-body {
        padding: 12px 14px 10px;
    }
    .orders-per-page-form i {
        color: #64748b;
        font-size: 18px;
    }
    .order-page-reload-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        border: none;
        border-radius: 10px;
        background: transparent;
        color: #64748b;
        padding: 0;
        cursor: pointer;
    }
    .order-page-reload-btn:hover {
        background: #f8fbff;
        color: #0f172a;
    }
    .orders-per-page-label {
        color: #475569;
        font-size: 13px;
        font-weight: 700;
        margin: 0;
    }
    .orders-per-page-select {
        min-width: 72px;
        height: 38px;
        border-radius: 10px;
        border: 1px solid #dbe6f3;
        font-size: 13px;
        font-weight: 700;
        color: #334155;
    }
    .orders-selection-count {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
    }
    .orders-selection-count strong {
        color: #4f46e5;
        font-size: 13px;
    }
    .orders-board .table {
        margin-bottom: 0;
    }
    .orders-board thead th {
        background: #f8fbff;
        color: #475569;
        font-size: 13px;
        font-weight: 700;
        border-bottom: 1px solid #dbe6f3;
        padding: 14px 12px;
        vertical-align: middle;
        white-space: nowrap;
    }
    .orders-board tbody td {
        padding: 14px 12px;
        vertical-align: top;
        border-top: 1px solid #edf2f7;
    }
    .order-invoice-id {
        font-size: 18px;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.1;
    }
    .order-meta-text,
    .order-muted-line {
        color: #64748b;
        font-size: 12px;
    }
    .order-reseller-store {
        margin-top: 8px;
        font-size: 14px;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.35;
    }
    .order-source-badge {
        display: inline-block;
        margin-top: 8px;
        padding: 4px 10px;
        border-radius: 999px;
        background: #dcfce7;
        color: #15803d;
        font-size: 12px;
        font-weight: 600;
    }
    .order-customer-name {
        font-size: 15px;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 4px;
    }
    .order-phone-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 999px;
        background: #eef2ff;
        color: #4f46e5;
        font-size: 12px;
        margin: 6px 0;
        text-decoration: none;
    }
    .order-phone-badge:hover,
    .order-phone-badge:focus {
        color: #4338ca;
        text-decoration: none;
    }
    .order-courier-summary-top {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 8px 0 6px;
        flex-wrap: wrap;
    }
    .order-courier-count {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 3px 10px;
        border-radius: 6px;
        background: #dcfce7;
        color: #16a34a;
        font-size: 12px;
        font-weight: 700;
    }
    .order-courier-count-regular {
        background: #e0f2fe;
        color: #0369a1;
    }
    .order-courier-count-fraud {
        background: #fee2e2;
        color: #b91c1c;
    }
    .order-courier-count-vip {
        background: #fef3c7;
        color: #b45309;
    }
    .order-fraud-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 22px;
        height: 22px;
        border-radius: 50%;
        border: 1px solid #4f46e5;
        color: #4f46e5;
        background: #eef2ff;
        font-size: 12px;
        padding: 0;
        cursor: pointer;
    }
    .order-fraud-btn:hover {
        background: #4f46e5;
        color: #fff;
    }
    .order-courier-summary-line {
        margin-top: 6px;
        font-size: 12px;
        font-weight: 700;
        color: #111827;
    }
    .order-courier-summary-line .success {
        color: #16a34a;
    }
    .order-courier-summary-line .cancel {
        color: #dc2626;
    }
    .order-courier-bar {
        position: relative;
        width: 100%;
        max-width: 230px;
        height: 6px;
        border-radius: 999px;
        background: #e5e7eb;
        overflow: hidden;
        margin-top: 6px;
    }
    .order-courier-bar-success,
    .order-courier-bar-cancel {
        position: absolute;
        top: 0;
        bottom: 0;
    }
    .order-courier-bar-success {
        left: 0;
        background: #16a34a;
    }
    .order-courier-bar-cancel {
        right: 0;
        background: #ef4444;
    }
    .order-more-items {
        display: inline-block;
        margin-top: 6px;
        padding: 3px 8px;
        border-radius: 8px;
        background: #fef2f2;
        color: #dc2626;
        font-size: 14px;
        font-weight: 800;
        line-height: 1.1;
    }
    .order-printed-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        margin-left: 8px;
        padding: 4px 10px;
        border-radius: 999px;
        background: #ecfdf5;
        color: #059669;
        font-size: 12px;
        font-weight: 800;
    }
    .fraud-summary-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
        margin-bottom: 18px;
    }
    .fraud-summary-card {
        background: #f3f4f6;
        border-radius: 10px;
        text-align: center;
        padding: 18px 12px;
    }
    .fraud-summary-value {
        font-size: 40px;
        line-height: 1;
        font-weight: 800;
        color: #1f2937;
    }
    .fraud-summary-label {
        margin-top: 10px;
        font-size: 14px;
        font-weight: 700;
        color: #4b5563;
    }
    .fraud-summary-table-wrap {
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        overflow: hidden;
    }
    .fraud-summary-table {
        margin: 0;
    }
    .fraud-summary-table thead th {
        background: #f3f4f6;
        color: #374151;
        font-size: 14px;
        font-weight: 800;
        border-bottom: 1px solid #e5e7eb;
        text-align: center;
    }
    .fraud-summary-table tbody td {
        vertical-align: middle;
        text-align: center;
        font-size: 15px;
    }
    .fraud-summary-table tbody td:first-child {
        text-align: left;
        font-weight: 700;
    }
    .fraud-summary-rate {
        font-weight: 800;
        color: #374151;
    }
    .fraud-summary-mobile {
        margin-bottom: 14px;
        font-size: 15px;
        color: #374151;
        font-weight: 700;
    }
    @media (max-width: 767px) {
        .fraud-summary-grid {
            grid-template-columns: 1fr;
        }
    }
    .order-product-wrap {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
        min-width: 210px;
    }
    .order-product-thumb {
        width: 72px;
        height: 72px;
        border-radius: 14px;
        object-fit: cover;
        background: #fff;
        border: 1px solid #e2e8f0;
        flex-shrink: 0;
    }
    .order-product-details {
        width: 100%;
    }
    .order-product-name {
        color: #334155;
        font-size: 14px;
        font-weight: 600;
        line-height: 1.45;
        margin-bottom: 4px;
        white-space: normal;
        word-break: break-word;
    }
    .order-money-line {
        font-size: 14px;
        color: #334155;
        margin-bottom: 6px;
    }
    .order-money-line strong {
        color: #0f172a;
    }
    .order-status-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 8px;
        background: #dbeafe;
        color: #1d4ed8;
        font-size: 12px;
        font-weight: 700;
        margin-left: 6px;
    }
    .order-track-btn {
        margin-top: 8px;
    }
    .order-action-menu-wrap {
        position: relative;
        display: inline-block;
    }
    .order-action-trigger {
        border: none;
        background: transparent;
        color: #14b8a6;
        font-size: 24px;
        line-height: 1;
        padding: 0 6px;
    }
    .order-action-trigger:hover {
        color: #0f766e;
    }
    .order-action-dropdown {
        position: absolute;
        right: 0;
        top: calc(100% + 8px);
        min-width: 140px;
        background: #ffffff;
        border: 1px solid #dbe6f3;
        border-radius: 8px;
        box-shadow: 0 16px 30px rgba(15, 23, 42, 0.14);
        padding: 8px;
        z-index: 1200;
        display: none;
    }
    .order-action-dropdown.show {
        display: block;
    }
    .order-menu-item {
        display: block;
        width: 100%;
        border: none;
        border-radius: 4px;
        padding: 7px 10px;
        margin-bottom: 6px;
        text-align: center;
        font-size: 12px;
        font-weight: 700;
        text-decoration: none;
        color: #fff;
        cursor: pointer;
    }
    .order-menu-item:last-child {
        margin-bottom: 0;
    }
    .order-menu-approved { background: #0b8f12; }
    .order-menu-processing { background: #2563eb; }
    .order-menu-pending { background: #fbbf24; color: #111827; }
    .order-menu-cancel { background: #ff4d57; }
    .order-menu-edit { background: #bfe8f0; color: #0f172a; }
    .order-menu-view { background: #000; }
    .order-menu-print { background: #2dd4bf; }
    .order-note-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border: none;
        background: transparent;
        color: #24c4c4;
        font-size: 18px;
        padding: 0;
    }
    .order-note-icon:hover {
        color: #0f9f9f;
    }
    .order-note-preview {
        margin-top: 4px;
        font-size: 11px;
        line-height: 1.35;
        color: #64748b;
        max-width: 140px;
        word-break: break-word;
    }
    @media (max-width: 1400px) {
        .orders-stats-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }
    @media (max-width: 991px) {
        .orders-hero-title {
            font-size: 26px;
        }
        .orders-stats-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .orders-hero-main {
            width: 100%;
            align-items: flex-start;
            flex-wrap: wrap;
        }
        .orders-hero-toolbar {
            width: 100%;
        }
        .orders-hero-actions {
            flex-wrap: wrap;
            justify-content: flex-start;
            margin-left: 0;
            width: 100%;
        }
        .orders-search-form {
            flex: 1 1 100%;
            min-width: 100%;
        }
        .orders-filter-grid {
            grid-template-columns: 1fr;
        }
    }
    @media (max-width: 575px) {
        .orders-hero,
        .orders-toolbar {
            padding: 14px;
        }
        .orders-stats-grid {
            grid-template-columns: 1fr;
        }
        .orders-top-btn,
        .orders-status-pill {
            width: 100%;
            justify-content: center;
        }
        .orders-courier-dropdown {
            width: 100%;
        }
        .orders-status-shortcuts {
            flex-wrap: wrap;
            overflow-x: visible;
        }
        .orders-search-wrap {
            flex-direction: column;
            align-items: stretch;
        }
    }
</style>
@endsection
@section('content')
@php
    $currentOrderSlug = strtolower((string) (request()->route('slug') ?? ($order_status->slug ?? 'all')));
    $queryWithoutPage = request()->except('page');
    $searchHiddenFilters = collect($queryWithoutPage)->except('keyword')->all();
    $hasAdvancedFilter = collect([
        request('filter_date_from'),
        request('filter_date_to'),
        request('filter_courier'),
        request('filter_district'),
        request('filter_order_source'),
        request('filter_creator'),
    ])->filter(function ($value) {
        return trim((string) $value) !== '';
    })->isNotEmpty();
    $visibleFilterGroups = collect([
        trim((string) request('filter_date_from')) !== '' || trim((string) request('filter_date_to')) !== '' ? 'date' : null,
        trim((string) request('filter_courier')) !== '' ? 'courier' : null,
        trim((string) request('filter_district')) !== '' ? 'district' : null,
        trim((string) request('filter_order_source')) !== '' ? 'order_source' : null,
        trim((string) request('filter_creator')) !== '' ? 'creator' : null,
    ])->filter()->values()->all();
    $statusShortcutDefinitions = [
        ['slug' => 'all', 'label' => 'All', 'match' => ['all']],
        ['slug' => 'new', 'label' => 'New', 'match' => ['new']],
        ['slug' => 'pending', 'label' => 'Pending', 'match' => ['pending']],
        ['slug' => 'processing', 'label' => 'Processing', 'match' => ['processing']],
        ['slug' => 'wfp', 'label' => 'WFP', 'match' => ['wfp']],
        ['slug' => 'on-the-way', 'label' => 'On The Way', 'match' => ['on-the-way', 'on_the_way', 'on the way']],
        ['slug' => 'in-courier', 'label' => 'In Courier', 'match' => ['in-courier', 'in_courier', 'in courier']],
        ['slug' => 'delivered', 'label' => 'Delivered', 'match' => ['delivered']],
        ['slug' => 'partial-delivered', 'label' => 'Partial', 'match' => ['partial-delivered', 'partial_delivered', 'partial delivered']],
        ['slug' => 'returned', 'label' => 'Returned', 'match' => ['returned', 'return']],
        ['slug' => 'cancelled', 'label' => 'Cancelled', 'match' => ['cancelled', 'cancel']],
        ['slug' => 'completed', 'label' => 'Completed', 'match' => ['completed', 'complete']],
    ];
    $statusShortcuts = collect($statusShortcutDefinitions)->map(function ($item) use ($orderstatus, $order_status, $allOrdersCount) {
        if ($item['slug'] === 'all') {
            $item['count'] = (int) ($allOrdersCount ?? 0);
            return $item;
        }

        $matchedStatus = $orderstatus->first(function ($status) use ($item) {
            $slug = strtolower((string) ($status->slug ?? ''));
            $name = strtolower((string) ($status->name ?? ''));
            $matchSet = array_map('strtolower', $item['match'] ?? [$item['slug']]);
            return in_array($slug, $matchSet, true) || in_array($name, $matchSet, true);
        });

        $item['count'] = (int) ($matchedStatus->orders_count ?? 0);
        return $item;
    });
    $isCancelledPage = strtolower((string) ($order_status->slug ?? '')) === 'cancelled'
        || str_contains(strtolower((string) ($order_status->name ?? '')), 'cancel');
@endphp
<div class="container-fluid">
    <div class="orders-page-shell">
        <div class="orders-hero">
            <div class="orders-hero-top">
                <div class="orders-hero-main">
                    <div>
                        <h1 class="orders-hero-title">Orders</h1>
                    </div>
                    <div class="orders-hero-toolbar">
                        <ul class="action2-btn list-unstyled d-flex gap-2 p-0 m-0 flex-wrap">
                            <li><a data-bs-toggle="modal" data-bs-target="#asignUser" class="btn rounded-pill btn-success"><i class="fe-plus"></i> Assign</a></li>
                            <li><a data-bs-toggle="modal" data-bs-target="#changeStatus" class="btn rounded-pill btn-primary"><i class="fe-plus"></i> Status</a></li>
                            @if($isCancelledPage)
                                <li><a href="{{ route('admin.order.bulk_destroy') }}" class="btn rounded-pill btn-danger order_delete"><i class="fe-plus"></i> Delete</a></li>
                            @endif
                            <li><a href="{{ route('admin.order.order_print') }}" class="btn rounded-pill btn-info multi_order_print"><i class="fe-printer"></i> Print</a></li>
                            <li><a href="{{ route('admin.order.order_pos_print') }}" class="btn rounded-pill btn-dark multi_order_pos_print"><i class="fe-printer"></i> POS Print</a></li>
                            @if($steadfast || $pathao_info || (isset($redx_info) && $redx_info) || (isset($paperfly_info) && $paperfly_info))
                                <li class="dropdown orders-courier-dropdown">
                                    <a href="#" class="orders-top-btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fe-truck"></i> Courier
                                    </a>
                                    <div class="dropdown-menu orders-courier-menu">
                                        @if($steadfast)
                                            <a href="{{ route('admin.bulk_courier', 'steadfast') }}?status=5" class="dropdown-item orders-courier-item multi_order_courier">
                                                <i class="fe-truck"></i> Steadfast
                                            </a>
                                        @endif
                                        @if($pathao_info)
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#pathao" class="dropdown-item orders-courier-item">
                                                <i class="fe-truck"></i> Pathao
                                            </a>
                                        @endif
                                        @if(isset($redx_info) && $redx_info)
                                            <a href="{{ route('admin.bulk_courier', 'redx') }}?status=5" class="dropdown-item orders-courier-item multi_order_courier">
                                                <i class="fe-truck"></i> RedX
                                            </a>
                                        @endif
                                        @if(isset($paperfly_info) && $paperfly_info)
                                            <a href="{{ route('admin.bulk_courier', 'paperfly') }}?status=5" class="dropdown-item orders-courier-item multi_order_courier">
                                                <i class="fe-truck"></i> Paperfly
                                            </a>
                                        @endif
                                    </div>
                                </li>
                            @endif
                            <li class="dropdown orders-courier-dropdown">
                                <a href="#" class="orders-top-btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fe-filter"></i> Filter
                                </a>
                                <div class="dropdown-menu orders-filter-menu">
                                    <a href="#" class="dropdown-item orders-filter-item" data-filter-target="date">
                                        <i class="fe-calendar"></i> Filter By Date
                                    </a>
                                    <a href="#" class="dropdown-item orders-filter-item" data-filter-target="courier">
                                        <i class="fe-truck"></i> Filter By Courier
                                    </a>
                                    <a href="#" class="dropdown-item orders-filter-item" data-filter-target="district">
                                        <i class="fe-map-pin"></i> Filter By District
                                    </a>
                                    <a href="#" class="dropdown-item orders-filter-item" data-filter-target="order_source">
                                        <i class="fe-droplet"></i> Filter By Order Source
                                    </a>
                                    <a href="#" class="dropdown-item orders-filter-item" data-filter-target="creator">
                                        <i class="fe-sliders"></i> Filter By Creator
                                    </a>
                                </div>
                            </li>
                        </ul>
                        <div class="orders-hero-actions">
                            <form class="orders-search-form" method="GET">
                                @foreach($searchHiddenFilters as $hiddenName => $hiddenValue)
                                    <input type="hidden" name="{{ $hiddenName }}" value="{{ $hiddenValue }}">
                                @endforeach
                                <div class="orders-search-shell">
                                    <span class="orders-search-icon"><i class="fe-search"></i></span>
                                    <input type="text" name="keyword" value="{{ request('keyword') }}" placeholder="Search order ID, customer, phone..." class="form-control orders-search-input">
                                    <span class="orders-search-hint">Ctrl /</span>
                                </div>
                            </form>
                            <a href="{{ route('admin.order.export_csv') }}" class="orders-top-btn multi_order_csv_export"><i class="fe-download"></i> Export</a>
                            <a href="{{ route('admin.order.create') }}" class="orders-top-btn orders-top-btn-primary"><i class="fe-plus"></i> Create Order</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="orders-filter-panel {{ $hasAdvancedFilter ? 'is-open' : '' }}" id="ordersFilterPanel">
                <form method="GET" class="orders-filter-form">
                    @if(request()->filled('keyword'))
                        <input type="hidden" name="keyword" value="{{ request('keyword') }}">
                    @endif
                    <div class="orders-filter-empty {{ empty($visibleFilterGroups) ? 'is-visible' : '' }}" id="ordersFilterEmpty">
                        Filter dropdown থেকে একটি option select করুন, তারপর Apply Filter চাপুন।
                    </div>
                    <div class="orders-filter-grid">
                        <div class="orders-filter-group {{ in_array('date', $visibleFilterGroups, true) ? 'is-visible' : '' }}" data-filter-group="date">
                            <div class="orders-filter-group-title">Filter By Date</div>
                            <div class="orders-filter-inline">
                                <input type="date" name="filter_date_from" value="{{ request('filter_date_from') }}" class="form-control">
                                <input type="date" name="filter_date_to" value="{{ request('filter_date_to') }}" class="form-control">
                            </div>
                        </div>
                        <div class="orders-filter-group {{ in_array('courier', $visibleFilterGroups, true) ? 'is-visible' : '' }}" data-filter-group="courier">
                            <div class="orders-filter-group-title">Filter By Courier</div>
                            <select name="filter_courier" class="form-control">
                                <option value="">Select Courier</option>
                                <option value="none" {{ request('filter_courier') === 'none' ? 'selected' : '' }}>Without Courier</option>
                                @foreach($filterCourierOptions ?? [] as $courierOption)
                                    <option value="{{ $courierOption }}" {{ request('filter_courier') === $courierOption ? 'selected' : '' }}>
                                        {{ ucfirst($courierOption) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="orders-filter-group {{ in_array('district', $visibleFilterGroups, true) ? 'is-visible' : '' }}" data-filter-group="district">
                            <div class="orders-filter-group-title">Filter By District</div>
                            <select name="filter_district" class="form-control">
                                <option value="">Select District</option>
                                @foreach($districtOptions ?? [] as $districtOption)
                                    <option value="{{ $districtOption }}" {{ request('filter_district') === $districtOption ? 'selected' : '' }}>
                                        {{ $districtOption }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="orders-filter-group {{ in_array('order_source', $visibleFilterGroups, true) ? 'is-visible' : '' }}" data-filter-group="order_source">
                            <div class="orders-filter-group-title">Filter By Order Source</div>
                            <select name="filter_order_source" class="form-control">
                                <option value="">Select Order Source</option>
                                @foreach($orderSourceFilterOptions ?? [] as $orderSourceOption)
                                    <option value="{{ $orderSourceOption }}" {{ request('filter_order_source') === $orderSourceOption ? 'selected' : '' }}>
                                        {{ $orderSourceOption }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="orders-filter-group {{ in_array('creator', $visibleFilterGroups, true) ? 'is-visible' : '' }}" data-filter-group="creator">
                            <div class="orders-filter-group-title">Filter By Creator</div>
                            <select name="filter_creator" class="form-control">
                                <option value="">Select Creator</option>
                                <option value="customer" {{ request('filter_creator') === 'customer' ? 'selected' : '' }}>Customer</option>
                                <option value="reseller" {{ request('filter_creator') === 'reseller' ? 'selected' : '' }}>Reseller</option>
                                @foreach($users as $creatorUser)
                                    <option value="admin:{{ $creatorUser->id }}" {{ request('filter_creator') === 'admin:' . $creatorUser->id ? 'selected' : '' }}>
                                        Admin - {{ $creatorUser->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="orders-filter-actions">
                        <button type="submit" class="orders-top-btn orders-top-btn-primary">
                            <i class="fe-filter"></i> Apply Filter
                        </button>
                        <a href="{{ route('admin.orders', ['slug' => request()->route('slug') ?? 'all']) }}" class="orders-top-btn">
                            <i class="fe-refresh-cw"></i> Reset Filter
                        </a>
                    </div>
                </form>
            </div>

            <div class="orders-toolbar">
                <div class="orders-toolbar-top">
                    <div class="orders-status-shortcuts">
                        @foreach($statusShortcuts as $shortcut)
                            @php
                                $statusPercent = ($shortcut['slug'] !== 'all' && (int) ($allOrdersCount ?? 0) > 0)
                                    ? (int) round(((int) $shortcut['count'] / (int) $allOrdersCount) * 100)
                                    : null;
                            @endphp
                            <a href="{{ route('admin.orders', array_merge(['slug' => $shortcut['slug']], $queryWithoutPage)) }}" class="orders-status-pill {{ $currentOrderSlug === $shortcut['slug'] ? 'active' : '' }}">
                                <i class="fe-file-text"></i>
                                <span class="orders-status-pill-text">
                                    <span>{{ $shortcut['label'] }}</span>
                                    @if(!is_null($statusPercent))
                                        <span class="orders-status-pill-percent">{{ $statusPercent }}%</span>
                                    @endif
                                </span>
                                <span class="orders-status-pill-count">{{ $shortcut['count'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <div class="row order_page">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">

                    <div class="table-responsive orders-board">
                        <div class="orders-table-topbar">
                            <form method="GET" class="orders-per-page-form">
                                @foreach(request()->except('per_page', 'page') as $hiddenName => $hiddenValue)
                                    <input type="hidden" name="{{ $hiddenName }}" value="{{ $hiddenValue }}">
                                @endforeach
                                <button type="button" class="order-page-reload-btn" title="Reload Orders">
                                    <i class="fe-refresh-cw"></i>
                                </button>
                                <span class="orders-per-page-label">Show</span>
                                <select name="per_page" class="form-control orders-per-page-select" onchange="this.form.submit()">
                                    @foreach([10, 25, 50, 100, 300, 500, 700, 1000] as $perPageOption)
                                        <option value="{{ $perPageOption }}" {{ (int) request('per_page', 10) === $perPageOption ? 'selected' : '' }}>
                                            {{ $perPageOption }}
                                        </option>
                                    @endforeach
                                </select>
                            </form>
                            <div class="orders-selection-count" id="ordersSelectionCount">
                                Selected: <strong id="ordersSelectionCountValue">0</strong>
                            </div>
                        </div>
                        <table id="datatable-buttons" class="table table-striped w-100">
                            <thead>
                                <tr>
                                    <th style="width:2%;">
                                        <div class="form-check">
                                            <label class="form-check-label">
                                                <input type="checkbox" class="form-check-input checkall" value="">
                                            </label>
                                        </div>
                                    </th>
                                    <th style="width:9%;">Invoice</th>
                                    <th style="width:17%;">Customer</th>
                                    <th style="width:18%;">Product</th>
                                    <th style="width:11%;">Total</th>
                                    <th style="width:16%;">Activities</th>
                                    <th style="width:9%;">Reseller</th>
                                    <th style="width:10%;">Courier</th>
                                    <th style="width:12%;">Note</th>
                                    <th style="width:12%;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $currentOrderSlug = strtolower((string) ($order_status->slug ?? ''));
                                    $currentOrderName = strtolower((string) ($order_status->name ?? ''));
                                    $isNewPage = $currentOrderSlug === 'new' || $currentOrderName === 'new';
                                    $isPendingPage = $currentOrderSlug === 'pending' || $currentOrderName === 'pending';
                                    $approvedStatusId = optional($orderstatus->first(function($s){ return in_array(strtolower($s->slug ?? ''), ['approved']) || strtolower($s->name ?? '') === 'approved'; }))->id;
                                    $processingStatusId = optional($orderstatus->first(function($s){ return in_array(strtolower($s->slug ?? ''), ['processing']) || strtolower($s->name ?? '') === 'processing'; }))->id;
                                    $pendingStatusId = optional($orderstatus->first(function($s){ return in_array(strtolower($s->slug ?? ''), ['pending']) || strtolower($s->name ?? '') === 'pending'; }))->id;
                                    $cancelStatusId = optional($orderstatus->first(function($s){ return in_array(strtolower($s->slug ?? ''), ['cancelled', 'cancel']) || in_array(strtolower($s->name ?? ''), ['cancelled', 'cancel']); }))->id;
                                    $quickPrimaryStatusId = $isPendingPage ? $processingStatusId : $approvedStatusId;
                                    $quickPrimaryStatusLabel = $isPendingPage ? 'Processing' : 'Approved';
                                @endphp
                                @foreach($show_data as $value)
                                    @php
                                        $items = $value->orderDetails;
                                        $firstItem = $items->first();
                                        $firstImage = optional($firstItem?->image)->image ? asset(optional($firstItem->image)->image) : asset('public/no-image.png');
                                        $productCount = $items->count();
                                        $customerName = $value->shipping ? $value->shipping->name : 'Walk-in Customer';
                                        $customerPhone = $value->shipping ? $value->shipping->phone : '-';
                                        $customerAddress = $value->shipping ? $value->shipping->address : '-';
                                        $orderSource = '-';
                                        $rawOrderSource = trim((string) ($value->note ?? ''));
                                        $legacyCourierNote = '';
                                        if ($rawOrderSource !== '' && preg_match('/^Order Source:\s*(.+)$/im', $rawOrderSource, $matches)) {
                                            $orderSource = trim($matches[1]);
                                            $legacyCourierNote = trim(preg_replace('/^Order Source:\s*.+$/im', '', $rawOrderSource));
                                        } elseif ($rawOrderSource !== '') {
                                            $orderSource = $rawOrderSource;
                                        }
                                        $payment = \App\Models\Payment::where('order_id', $value->id)->first();
                                        $paymentStatus = strtolower((string) optional($payment)->payment_status);
                                        $isPaidOrder = in_array($paymentStatus, ['paid', 'completed', 'success']);
                                        $total = floatval($value->amount);
                                        $paid = $isPaidOrder ? floatval($payment->amount) : 0;
                                        $deliveryCharge = (float) ($value->shipping_charge ?? 0);
                                        $productPrice = max(0, $total - $deliveryCharge);
                                        $codAmount = $productPrice + $deliveryCharge;
                                        $due = max(0, $total - $paid);
                                        $statusName = $value->status ? $value->status->name : 'Unknown';
                                        $trackingId = isset($value->courier_tracking_id) ? $value->courier_tracking_id : $value->consignment_id;
                                        $courierType = $value->courier_type;
                                        if (!$courierType && $value->consignment_id) {
                                            $courierType = 'steadfast';
                                        }
                                                $isResellerOrder = !is_null($value->reseller_profit) && (float) $value->reseller_profit > 0 && $value->user;
                                                $resellerName = $isResellerOrder ? $value->user->name : null;
                                                $resellerStoreName = $isResellerOrder ? trim((string) ($value->user->shop_name ?? '')) : '';
                                                if ($value->createdByAdmin) {
                                                    $createdByLabel = 'Admin - ' . $value->createdByAdmin->name;
                                                } elseif ($isResellerOrder) {
                                                    $createdByLabel = 'Reseller';
                                                } else {
                                                    $createdByLabel = 'Customer';
                                                }
                                                if ($orderSource === '-' && !$value->createdByAdmin && !$isResellerOrder) {
                                                    $orderSource = 'Website';
                                                }
                                                $lifetimeBadge = ($orderSource === 'Website' && $customerPhone !== '-')
                                                    ? ($customerLifetimeBadges[$customerPhone] ?? null)
                                                    : null;
                                        $adminNoteText = trim((string) ($value->admin_note ?? ''));
                                        $adminNoteText = trim(str_replace('[PRINTED]', '', $adminNoteText));
                                        $adminNotePreview = '';
                                        if (!empty($adminNoteText)) {
                                            $words = preg_split('/\s+/', trim(strip_tags($adminNoteText)));
                                            $adminNotePreview = implode(' ', array_slice($words, 0, 3));
                                            if (count($words) > 3) {
                                                $adminNotePreview .= '...';
                                            }
                                        }
                                        $isPrinted = str_contains((string) ($value->admin_note ?? ''), '[PRINTED]');
                                        $storedFraudSummary = $persistedFraudSummaries[$customerPhone] ?? null;
                                        $fraudDelivered = (int) ($storedFraudSummary['success'] ?? $value->fraud_success ?? 0);
                                        $fraudReturned = (int) ($storedFraudSummary['cancel'] ?? $value->fraud_cancel ?? 0);
                                        $fraudTotal = (int) ($storedFraudSummary['total'] ?? ($fraudDelivered + $fraudReturned));
                                        $fraudDeliveredWidth = $fraudTotal > 0 ? round(($fraudDelivered / $fraudTotal) * 100, 2) : 0;
                                        $fraudReturnedWidth = $fraudTotal > 0 ? round(($fraudReturned / $fraudTotal) * 100, 2) : 0;
                                    @endphp
                                    <tr>
                                        <td><input type="checkbox" class="checkbox form-check-input" value="{{ $value->id }}"></td>
                                        <td>
                                            <div class="order-invoice-id">{{ $value->invoice_id }}</div>
                                            <div class="order-meta-text mt-1">{{ date('d-m-Y', strtotime($value->updated_at)) }}</div>
                                            <div class="order-meta-text">{{ date('h:i:s a', strtotime($value->updated_at)) }}</div>
                                            <span class="order-source-badge">{{ $orderSource }}</span>
                                        </td>
                                        <td>
                                            <div class="order-customer-name">{{ $customerName }}</div>
                                            <div class="order-courier-summary-top">
                                                <span class="order-courier-count {{ $lifetimeBadge['class'] ?? '' }}" data-fraud-total="{{ $customerPhone }}" @if($lifetimeBadge) data-lifetime-badge="1" @endif>{{ $lifetimeBadge['label'] ?? ('Courier Orders ' . $fraudTotal) }}</span>
                                            </div>
                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                @if($customerPhone !== '-')
                                                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $customerPhone) }}" class="order-phone-badge"><i class="fe-phone-call"></i> {{ $customerPhone }}</a>
                                                @else
                                                    <span class="order-phone-badge"><i class="fe-phone-call"></i> {{ $customerPhone }}</span>
                                                @endif
                                                @if($customerPhone !== '-')
                                                    <button type="button" class="fraud-check order-fraud-btn" data-mobile="{{ $customerPhone }}" title="Courier Fraud Summary">
                                                        <i class="fe-info"></i>
                                                    </button>
                                                @endif
                                            </div>
                                            <div class="order-muted-line">{{ $customerAddress }}</div>
                                            <div class="order-courier-summary-line" data-fraud-line="{{ $customerPhone }}">
                                                ALL: {{ $fraudTotal }}
                                                <span class="success">| Delivered: {{ $fraudDelivered }}</span>
                                                <span class="cancel">| Return: {{ $fraudReturned }}</span>
                                            </div>
                                            <div class="order-courier-bar" data-fraud-bar="{{ $customerPhone }}">
                                                <span class="order-courier-bar-success" data-fraud-success-bar="{{ $customerPhone }}" style="width: {{ $fraudDeliveredWidth }}%;"></span>
                                                <span class="order-courier-bar-cancel" data-fraud-cancel-bar="{{ $customerPhone }}" style="width: {{ $fraudReturnedWidth }}%;"></span>
                                            </div>
                                            @if($value->ip_address)
                                                <div class="order-muted-line mt-1">IP: {{ $value->ip_address }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="order-product-wrap">
                                                <img src="{{ $firstImage }}" alt="Product" class="order-product-thumb">
                                                <div class="order-product-details">
                                                    <div class="order-product-name">{{ $firstItem ? $firstItem->product_name : 'No product' }}</div>
                                                    @if($productCount > 1)
                                                        <div class="order-more-items">+{{ $productCount - 1 }} more item(s)</div>
                                                    @endif
                                                    <div class="order-muted-line">Qty: {{ $items->sum('qty') }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="order-money-line">Price : <strong>৳{{ number_format($productPrice, 2) }}</strong></div>
                                            <div class="order-money-line">Delivery Charge : <strong>৳{{ number_format($deliveryCharge, 2) }}</strong></div>
                                            <div class="order-money-line mb-0">COD : <strong>৳{{ number_format($codAmount, 2) }}</strong></div>
                                        </td>
                                        <td>
                                            <div class="order-muted-line">Status : <span class="order-status-badge">{{ $statusName }}</span></div>
                                            @if($isPrinted)
                                                <div class="order-printed-badge"><i class="fe-printer"></i> Printed</div>
                                            @endif
                                            <div class="order-muted-line mt-1">Created By : {{ $createdByLabel }}</div>
                                            @if($value->admin_note)
                                                <div class="order-muted-line mt-1">Admin Note Added</div>
                                            @endif
                                        </td>
                                        <td>
                                            @if($resellerName)
                                                <span class="badge bg-info"><i class="fe-user"></i> {{ Str::limit($resellerName, 18) }}</span>
                                                @if($resellerStoreName !== '')
                                                    <div class="order-reseller-store">{{ $resellerStoreName }}</div>
                                                @endif
                                                <div class="order-muted-line mt-2">Profit : ৳{{ number_format($value->reseller_profit, 0) }}</div>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($trackingId)
                                                @php
                                                    $courierName = ucfirst(isset($courierType) ? $courierType : 'Steadfast');
                                                    $ct = isset($courierType) ? strtolower($courierType) : 'steadfast';
                                                    if ($ct === 'pathao') { $courierColor = 'info'; }
                                                    elseif ($ct === 'steadfast') { $courierColor = 'primary'; }
                                                    elseif ($ct === 'redx') { $courierColor = 'warning'; }
                                                    elseif ($ct === 'paperfly') { $courierColor = 'secondary'; }
                                                    else { $courierColor = 'secondary'; }
                                                @endphp
                                                <span class="badge bg-{{ $courierColor }}"><i class="fe-truck"></i> {{ $courierName }}</span>
                                                <div class="order-muted-line mt-2">ID : {{ Str::limit($trackingId, 16) }}</div>
                                                @if($courierType == 'pathao')
                                                    <a href="https://merchant.pathao.com/public-tracking?consignment_id={{ $trackingId }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-info order-track-btn">Track</a>
                                                @elseif($courierType == 'steadfast' || (!$courierType && $trackingId))
                                                    <a href="https://steadfast.com.bd/t/{{ $trackingId }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary order-track-btn">Track</a>
                                                @elseif($courierType == 'redx')
                                                    <a href="https://redx.com.bd/track/{{ $trackingId }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-warning order-track-btn">Track</a>
                                                @elseif($courierType == 'paperfly')
                                                    <a href="https://go.paperfly.com.bd/track/order/{{ $trackingId }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-secondary order-track-btn">Track</a>
                                                @endif
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            <button
                                                type="button"
                                                class="note-modal-btn order-note-icon"
                                                data-type="admin"
                                                data-id="{{ $value->id }}"
                                                data-note="{{ $adminNoteText }}"
                                                title="{{ $adminNoteText ? 'View Admin Note' : 'Add Admin Note' }}"
                                            >
                                                <i class="fe-edit-2"></i>
                                            </button>
                                            @if($adminNotePreview)
                                                <div class="order-note-preview">{{ $adminNotePreview }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="order-action-menu-wrap">
                                                <button type="button" class="order-action-trigger" data-order-menu-toggle>&bull;&bull;&bull;</button>
                                                <div class="order-action-dropdown">
                                                    @if($quickPrimaryStatusId)
                                                        <button type="button" class="order-menu-item order-menu-approved single-status-change" data-order-id="{{ $value->id }}" data-status-id="{{ $quickPrimaryStatusId }}">{{ $quickPrimaryStatusLabel }}</button>
                                                    @endif
                                                    @if($processingStatusId && $isNewPage)
                                                        <button type="button" class="order-menu-item order-menu-processing single-status-change" data-order-id="{{ $value->id }}" data-status-id="{{ $processingStatusId }}">Processing</button>
                                                    @endif
                                                    @if($pendingStatusId && !$isPendingPage)
                                                        <button type="button" class="order-menu-item order-menu-pending single-status-change" data-order-id="{{ $value->id }}" data-status-id="{{ $pendingStatusId }}">Pending</button>
                                                    @endif
                                                    @if($cancelStatusId)
                                                        <button type="button" class="order-menu-item order-menu-cancel single-status-change" data-order-id="{{ $value->id }}" data-status-id="{{ $cancelStatusId }}">Cancel</button>
                                                    @endif
                                                    <a href="{{ route('admin.order.edit', ['invoice_id' => $value->invoice_id]) }}" class="order-menu-item order-menu-edit">Edit</a>
                                                    <a href="{{ route('admin.order.invoice', ['invoice_id' => $value->invoice_id]) }}" class="order-menu-item order-menu-view">View</a>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="custom-paginate mt-3">
                        {{ $show_data->appends(request()->except('page'))->links('pagination::bootstrap-4') }}
                    </div>
                </div> </div> </div></div>
</div>

<div class="modal fade" id="asignUser" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Assign User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="{{ route('admin.order.assign') }}" id="order_assign">
        <div class="modal-body">
            <div class="form-group">
                <select name="user_id" id="user_id" class="form-control">
                    <option value="">Select..</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-success">Submit</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="changeStatus" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Change Status</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="{{ route('admin.order.status') }}" id="order_status_form" novalidate>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Select Status <span class="text-danger">*</span></label>
                <select name="order_status" id="order_status" class="form-control">
                    <option value="">Select Status..</option>
                    @if(isset($orderstatus) && $orderstatus->count() > 0)
                        @foreach($orderstatus as $s)
                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                        @endforeach
                    @else
                        <option value="">No status available</option>
                    @endif
                </select>
                <small class="text-muted">Select orders first, then choose status</small>
                <div class="invalid-feedback" id="status_error" style="display: none;">Please select a status</div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-success">Update Status</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="pathao" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Pathao Courier</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="{{ route('admin.order.pathao') }}" id="order_sendto_pathao" method="POST">
      @csrf
      <input type="hidden" name="order_ids" id="pathao_order_ids" value="">
      <div class="modal-body">
        <div class="form-group">
            <label for="pathaostore" class="form-label">Store</label>
           <select name="pathaostore" id="pathaostore" class="pathaostore form-control" >
             <option value="">Select Store...</option>
             @if(isset($pathaostore['data']['data']))
                 @foreach($pathaostore['data']['data'] as $store)
                     <option value="{{ $store['store_id'] }}">{{ $store['store_name'] }}</option>
                 @endforeach
             @endif
           </select>
        </div>
        <p class="text-muted small mt-3 mb-0">
          Selected order-এর address অনুযায়ী city, zone, area automatically match করে Pathao-তে পাঠানো হবে।
        </p>
        <div class="form-check mt-3">
          <input class="form-check-input" type="checkbox" value="1" id="pathao_manual_toggle">
          <label class="form-check-label" for="pathao_manual_toggle">
            Manual city / zone / area select
          </label>
        </div>
        <div id="pathao_manual_fields" class="mt-3" style="display:none;">
          <div class="form-group">
            <label for="pathaocity" class="form-label">City</label>
            <select name="pathaocity" id="pathaocity" class="chosen-select pathaocity form-control" style="width:100%">
              <option value="">Select City...</option>
              @if(isset($pathaocities['data']['data']))
                  @foreach($pathaocities['data']['data'] as $city)
                      <option value="{{ $city['city_id'] }}">{{ $city['city_name'] }}</option>
                  @endforeach
              @endif
            </select>
          </div>
          <div class="form-group mt-3">
            <label for="pathaozone" class="form-label">Zone</label>
            <select name="pathaozone" id="pathaozone" class="chosen-select pathaozone form-control" style="width:100%">
              <option value="">Select Zone...</option>
            </select>
          </div>
          <div class="form-group mt-3">
            <label for="pathaoarea" class="form-label">Area</label>
            <select name="pathaoarea" id="pathaoarea" class="chosen-select pathaoarea form-control" style="width:100%">
              <option value="">Select Area...</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="submit" class="btn btn-success">Send To Pathao</button>
      </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="noteModal" tabindex="-1" aria-labelledby="noteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="noteModalLabel">Admin Note</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" id="note_order_id">
        <input type="hidden" id="note_type">

        <div class="table-responsive mb-4">
            <table class="table table-bordered align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:10%;">#SL</th>
                        <th>Comments</th>
                        <th style="width:20%;">Comment By</th>
                        <th style="width:22%;">Created At</th>
                    </tr>
                </thead>
                <tbody id="admin_note_history_body">
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">No comments found</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="form-group mb-3">
            <label for="note_select_comment" id="note_label">Select Comment</label>
            <select id="note_select_comment" class="form-control">
                <option value="">Select Comment</option>
                @foreach($adminNotePresets as $preset)
                    <option value="{{ $preset }}">{{ $preset }}</option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label for="note_custom_comment">Write Comment (Optional)</label>
            <textarea id="note_custom_comment" class="form-control" rows="3" placeholder="Write Comment"></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-success" id="saveNoteBtn">submit</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="fraudCheckModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content" style="border-radius:12px;">
            <div class="modal-header" style="background:#10b981; color:#fff;">
                <h5 class="modal-title">
                    <i class="fe-shield"></i> Fraud Check Report
                </h5>
                <button type="button" class="btn-close btn-light" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body" id="fraudModalBody" style="min-height:250px;">
                <div class="text-center py-5">
                    <div class="spinner-border text-success" style="width:3rem;height:3rem;"></div>
                    <p class="mt-3 fw-bold">Data loading...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function toNum(v) {
        if (v === null || v === undefined || v === '') return 0;
        var n = Number(v);
        return isNaN(n) ? 0 : n;
    }

    function buildSummary(raw) {
        var pathao = raw.pathao || raw.Pathao || raw.pathao_data || {};
        var redx = raw.redx || raw.RedX || raw.redx_data || {};
        var steadfast = raw.steadfast || raw.Steadfast || raw.steadfast_data || {};
        var parceldex = raw.parceldex || raw.ParcelDex || {};
        var paperfly = raw.paperfly || raw.PaperFly || {};
        var carrybee = raw.carrybee || raw.CarryBee || {};
        var summary = raw.summary || {};

        function getStats(obj) {
            var t = toNum(obj.total_parcel || obj.total || obj.orders || obj.count);
            var s = toNum(obj.success_parcel || obj.success || obj.complete || obj.delivered);
            var c = toNum(obj.cancelled_parcel || obj.cancel || obj.cancelled || obj.failed);
            var r = (obj.success_ratio !== undefined) ? toNum(obj.success_ratio) : (t > 0 ? Math.round((s / t) * 100) : 0);
            return { total: t, success: s, cancel: c, rate: r };
        }

        var p = getStats(pathao);
        var r = getStats(redx);
        var s = getStats(steadfast);
        var pd = getStats(parceldex);
        var pf = getStats(paperfly);
        var cb = getStats(carrybee);

        var total = toNum(summary.total_parcel) || (p.total + r.total + s.total + pd.total + pf.total + cb.total);
        var success = toNum(summary.success_parcel) || (p.success + r.success + s.success + pd.success + pf.success + cb.success);
        var cancel = toNum(summary.cancelled_parcel) || (p.cancel + r.cancel + s.cancel + pd.cancel + pf.cancel + cb.cancel);
        var rate = (summary.success_ratio !== undefined && summary.success_ratio !== null && summary.success_ratio !== '')
            ? toNum(summary.success_ratio)
            : (total > 0 ? Math.round((success / total) * 100) : 0);

        return {
            total: total,
            success: success,
            cancel: cancel,
            rate: rate,
            couriers: { Pathao: p, RedX: r, Steadfast: s, ParcelDex: pd, PaperFly: pf, CarryBee: cb }
        };
    }

    function loadFraudHtml(data, mobile) {
        if (data.total === 0) {
            return '<div class="alert alert-light text-center p-4">No fraud history found for ' + mobile + '.</div>';
        }

        var courierRows = '';
        Object.entries(data.couriers).forEach(function(entry) {
            var name = entry[0];
            var c = entry[1];
            if (c.total === 0) return;
            courierRows += '<tr><td>' + name + '</td><td>' + c.total + '</td><td class="text-success fw-bold">' + c.success + '</td><td class="text-danger fw-bold">' + c.cancel + '</td><td class="fraud-summary-rate">' + c.rate + '%</td></tr>';
        });

        return '<div class="container-fluid px-1">' +
            '<div class="fraud-summary-mobile">Mobile: ' + mobile + '</div>' +
            '<div class="fraud-summary-grid">' +
                '<div class="fraud-summary-card"><div class="fraud-summary-value">' + data.total + '</div><div class="fraud-summary-label">Total Orders</div></div>' +
                '<div class="fraud-summary-card"><div class="fraud-summary-value">' + data.success + '</div><div class="fraud-summary-label">Total Delivered</div></div>' +
                '<div class="fraud-summary-card"><div class="fraud-summary-value">' + data.cancel + '</div><div class="fraud-summary-label">Total Return</div></div>' +
            '</div>' +
            '<div class="fraud-summary-table-wrap">' +
                '<table class="table fraud-summary-table">' +
                    '<thead><tr><th>Courier</th><th>Order</th><th>Delivered</th><th>Return</th><th>Success Rate</th></tr></thead><tbody>' + courierRows + '</tbody>' +
                '</table>' +
            '</div>' +
            '</div>';
    }

    function refreshFraudSummaryRow(mobile, data) {
        var deliveredWidth = data.total > 0 ? ((data.success / data.total) * 100) : 0;
        var cancelWidth = data.total > 0 ? ((data.cancel / data.total) * 100) : 0;

        $('[data-fraud-total="' + mobile + '"]').each(function() {
            if ($(this).data('lifetime-badge') == 1) {
                return;
            }
            $(this).text('Courier Orders ' + data.total);
        });
        $('[data-fraud-line="' + mobile + '"]').html(
            'ALL: ' + data.total +
            ' <span class="success">| Delivered: ' + data.success + '</span>' +
            ' <span class="cancel">| Return: ' + data.cancel + '</span>'
        );
        $('[data-fraud-success-bar="' + mobile + '"]').css('width', deliveredWidth + '%');
        $('[data-fraud-cancel-bar="' + mobile + '"]').css('width', cancelWidth + '%');
    }
</script>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function(){
    function renderAdminNoteHistory(notes) {
        let rows = '';

        if (!notes || !notes.length) {
            rows = '<tr><td colspan="4" class="text-center text-muted py-4">No comments found</td></tr>';
        } else {
            notes.forEach(function(note) {
                rows += '<tr>' +
                    '<td>' + note.sl + '</td>' +
                    '<td>' + $('<div>').text(note.comment || '').html() + '</td>' +
                    '<td>' + $('<div>').text(note.comment_by || 'Admin').html() + '</td>' +
                    '<td>' + $('<div>').text(note.created_at || '').html() + '</td>' +
                '</tr>';
            });
        }

        $('#admin_note_history_body').html(rows);
    }

    $(document).on('click', '.note-modal-btn', function (e) {
        e.preventDefault();
        let orderId = $(this).data('id');
        let type = $(this).data('type');

        $('#note_order_id').val(orderId);
        $('#note_type').val(type);
        $('#noteModalLabel').text('Order Comment');
        $('#note_label').text('Select Comment');
        $('#note_select_comment').val('');
        $('#note_custom_comment').val('');
        $('#admin_note_history_body').html('<tr><td colspan="4" class="text-center text-muted py-4">Loading...</td></tr>');

        $('#noteModal').modal('show');

        $.ajax({
            url: "{{ route('admin.order.admin_note_history') }}",
            type: "GET",
            data: { order_id: orderId },
            success: function (res) {
                if (res.status === 'success') {
                    renderAdminNoteHistory(res.notes || []);
                } else {
                    $('#admin_note_history_body').html('<tr><td colspan="4" class="text-center text-danger py-4">Failed to load comments</td></tr>');
                }
            },
            error: function () {
                $('#admin_note_history_body').html('<tr><td colspan="4" class="text-center text-danger py-4">Failed to load comments</td></tr>');
            }
        });
    });

    $('#saveNoteBtn').on('click', function () {
        let orderId = $('#note_order_id').val();
        let type = $('#note_type').val();
        let selectedComment = $('#note_select_comment').val();
        let customComment = $('#note_custom_comment').val();

        $.ajax({
            url: "{{ route('admin.order.update_note') }}",
            type: "POST",
            data: {
                _token: "{{ csrf_token() }}",
                order_id: orderId,
                note_type: type,
                selected_comment: selectedComment,
                custom_comment: customComment
            },
            success: function (res) {
                if (res.status === 'success') {
                    toastr.success('Admin note updated successfully');
                    let selector = '.note-modal-btn[data-id="' + orderId + '"][data-type="' + type + '"]';
                    let $btn = $(selector);
                    let note = res.note || '';
                    let preview = note.split(/\s+/).slice(0, 3).join(' ');
                    if (note.split(/\s+/).length > 3) {
                        preview += '...';
                    }
                    $btn.data('note', note);
                    $btn.attr('title', note ? 'View Admin Note' : 'Add Admin Note');

                    let $preview = $btn.closest('td').find('.order-note-preview');
                    if (note) {
                        if ($preview.length) {
                            $preview.text(preview);
                        } else {
                            $btn.closest('td').append('<div class="order-note-preview">' + $('<div>').text(preview).html() + '</div>');
                        }
                    } else {
                        $preview.remove();
                    }

                    $('#note_select_comment').val('');
                    $('#note_custom_comment').val('');

                    $.ajax({
                        url: "{{ route('admin.order.admin_note_history') }}",
                        type: "GET",
                        data: { order_id: orderId },
                        success: function(historyRes) {
                            if (historyRes.status === 'success') {
                                renderAdminNoteHistory(historyRes.notes || []);
                            }
                        }
                    });

                    $('#noteModal').modal('hide');
                } else {
                    toastr.error(res.message || 'Update failed');
                }
            },
            error: function (xhr) {
                let message = 'Something went wrong';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                toastr.error(message);
            }
        });
    });

    $(".checkall").on('change',function(){
      $(".checkbox").prop('checked',$(this).is(":checked"));
    });

    $(document).on('click', '.fraud-check', function(e){
        e.preventDefault();
        let mobile = $(this).data('mobile');
        if (!mobile) { return toastr.error("No mobile number found"); }

        $("#fraudModalBody").html('<div class="text-center py-5"><div class="spinner-border text-success" style="width:3rem;height:3rem;"></div><p class="mt-3 fw-bold">Checking...</p></div>');
        $("#fraudCheckModal").modal("show");

        $.ajax({
            url: "{{ route('admin.fraud.check') }}",
            type: "POST",
            data: { mobile: mobile, _token: "{{ csrf_token() }}" },
            timeout: 60000,
            success: function(res) {
                if (res && res.status === "success") {
                    let apiData = res.data && res.data.data ? res.data.data : (res.data || {});
                    let summary = buildSummary(apiData);
                    refreshFraudSummaryRow(mobile, summary);
                    $("#fraudModalBody").html(loadFraudHtml(summary, mobile));
                } else {
                    $("#fraudModalBody").html('<div class="alert alert-danger text-center p-4">' + ((res && res.message) ? res.message : 'No data returned') + '</div>');
                }
            },
            error: function(xhr, status) {
                let errorMessage = status === 'timeout' ? 'Request timeout' : 'Fraud check failed';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                $("#fraudModalBody").html('<div class="alert alert-danger text-center p-4">' + errorMessage + '</div>');
                toastr.error(errorMessage);
            }
        });
    });

    $(document).on('click', '[data-order-menu-toggle]', function(e){
        e.preventDefault();
        e.stopPropagation();
        var $dropdown = $(this).siblings('.order-action-dropdown');
        $('.order-action-dropdown').not($dropdown).removeClass('show');
        $dropdown.toggleClass('show');
    });

    $(document).on('click', function(){
        $('.order-action-dropdown').removeClass('show');
    });

    $(document).on('click', '.order-action-dropdown', function(e){
        e.stopPropagation();
    });

    $(document).on('click', '.single-status-change', function(e){
        e.preventDefault();
        var orderId = $(this).data('order-id');
        var statusId = $(this).data('status-id');

        $.ajax({
            url: "{{ route('admin.order.updateSingleStatus') }}",
            type: "POST",
            data: {
                _token: "{{ csrf_token() }}",
                order_id: orderId,
                order_status: statusId
            },
            success: function(res){
                if(res.status === 'success'){
                    toastr.success(res.message || 'Status updated');
                    setTimeout(function(){ window.location.reload(); }, 700);
                } else {
                    toastr.error(res.message || 'Status update failed');
                }
            },
            error: function(xhr){
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Status update failed');
            }
        });
    });

    $(document).on('click', '.single-order-print', function(e){
        e.preventDefault();
        var orderId = $(this).data('order-id');

        $.ajax({
           type: 'GET',
           url: "{{ route('admin.order.order_print') }}",
           data: { order_ids: [orderId] },
           success: function(res){
               if(res.status == 'success'){
                   var myWindow = window.open("", "_blank");
                   myWindow.document.write(res.view);
                   setTimeout(function(){ window.location.reload(); }, 700);
               } else {
                   toastr.error(res.message || 'Print failed');
               }
           },
           error: function(){
               toastr.error('Print failed');
           }
        });
    });

    $(document).on('click', '.single-order-pos-print', function(e){
        e.preventDefault();
        var orderId = $(this).data('order-id');

        $.ajax({
           type: 'GET',
           url: "{{ route('admin.order.order_pos_print') }}",
           data: { order_ids: [orderId] },
           success: function(res){
               if(res.status == 'success'){
                   var myWindow = window.open("", "_blank");
                   myWindow.document.write(res.view);
                   setTimeout(function(){ window.location.reload(); }, 700);
               } else {
                   toastr.error(res.message || 'POS print failed');
               }
           },
           error: function(){
               toastr.error('POS print failed');
           }
        });
    });

    $(document).on('submit', 'form#order_assign', function(e){
        e.preventDefault();
        var url = $(this).attr('action');
        let user_id = $('#user_id').val();
        var order_ids = $('input.checkbox:checked').map(function(){ return $(this).val(); }).get();
        if(order_ids.length == 0){
            toastr.error('Please Select An Order First !');
            return;
        }
        $.ajax({
           type: 'GET',
           url: url,
           data: { user_id: user_id, order_ids: order_ids },
           success: function(res){
               if(res.status == 'success'){
                   toastr.success(res.message);
                   window.location.reload();
               } else {
                   toastr.error(res.message || 'Failed something wrong');
               }
           },
           error: function(){
               toastr.error('Something went wrong');
           }
        });
    });

    $(document).on('submit', 'form#order_status_form', function(e){
        e.preventDefault();
        var url = $(this).attr('action');
        let order_status = $('#order_status').val();
        var order_ids = $('input.checkbox:checked').map(function(){ return $(this).val(); }).get();
        if(order_ids.length == 0){
            toastr.error('Please Select An Order First !');
            return false;
        }
        if(!String(order_status || '').trim()){
            toastr.error('Please Select A Status First !');
            return false;
        }
        $.ajax({
           type: 'GET',
           url: url,
           data: { order_status: order_status, order_ids: order_ids },
           success: function(res){
               if(res.status == 'success'){
                   toastr.success(res.message);
                   $('#changeStatus').modal('hide');
                   setTimeout(function(){ window.location.reload(); }, 1000);
               } else {
                   toastr.error(res.message || 'Failed something wrong');
               }
           },
           error: function(){
               toastr.error('Something went wrong');
           }
        });
        return false;
    });

    $(document).on('click', '.order_delete', function(e){
        e.preventDefault();
        var url = $(this).attr('href');
        var order_ids = $('input.checkbox:checked').map(function(){ return $(this).val(); }).get();
        if(order_ids.length == 0){
            toastr.error('Please Select An Order First !');
            return;
        }
        $.ajax({
           type: 'GET',
           url: url,
           data: { order_ids: order_ids },
           success: function(res){
               if(res.status == 'success'){
                   toastr.success(res.message);
                   window.location.reload();
               } else {
                   toastr.error(res.message || 'Failed something wrong');
               }
           },
           error: function(){
               toastr.error('Something went wrong');
           }
        });
    });

    $(document).on('click', '.multi_order_print', function(e){
        e.preventDefault();
        var url = $(this).attr('href');
        var order_ids = $('input.checkbox:checked').map(function(){ return $(this).val(); }).get();
        if(order_ids.length == 0){
            toastr.error('Please Select Atleast One Order!');
            return;
        }
        $.ajax({
           type: 'GET',
           url: url,
           data: { order_ids: order_ids },
           success: function(res){
               if(res.status == 'success'){
                   var myWindow = window.open("", "_blank");
                   myWindow.document.write(res.view);
                   setTimeout(function(){ window.location.reload(); }, 700);
               } else {
                   toastr.error(res.message || 'Failed something wrong');
               }
           },
           error: function(){
               toastr.error('Something went wrong');
           }
        });
    });

    $(document).on('click', '.multi_order_csv_export', function(e){
        e.preventDefault();
        var url = $(this).attr('href');
        var order_ids = $('input.checkbox:checked').map(function(){ return $(this).val(); }).get();
        if(order_ids.length == 0){
            toastr.error('Please Select Atleast One Order!');
            return;
        }

        var query = $.param({ order_ids: order_ids });
        window.location.href = url + '?' + query;
    });

    $(document).on('click', '.multi_order_pos_print', function(e){
        e.preventDefault();
        var url = $(this).attr('href');
        var order_ids = $('input.checkbox:checked').map(function(){ return $(this).val(); }).get();
        if(order_ids.length == 0){
            toastr.error('Please Select Atleast One Order!');
            return;
        }
        $.ajax({
           type: 'GET',
           url: url,
           data: { order_ids: order_ids },
           success: function(res){
               if(res.status == 'success'){
                   var myWindow = window.open("", "_blank");
                   myWindow.document.write(res.view);
                   setTimeout(function(){ window.location.reload(); }, 700);
               } else {
                   toastr.error(res.message || 'POS print failed');
               }
           },
           error: function(){
               toastr.error('POS print failed');
           }
        });
    });

    $(document).on('click', '.multi_order_courier', function(e){
        e.preventDefault();
        var url = $(this).attr('href');
        var order_ids = $('input.checkbox:checked').map(function(){ return $(this).val(); }).get();
        if(order_ids.length == 0){
            toastr.error('Please Select An Order First !');
            return;
        }
        $.ajax({
           type: 'GET',
           url: url,
           data: { order_ids: order_ids },
           success: function(res){
               if(res.status == 'success'){
                    toastr.success(res.message || 'Orders sent to courier successfully!');
                    setTimeout(function(){ window.location.reload(); }, 1000);
               } else if (res.status == 'partial') {
                    var partialMessage = res.message || 'Some orders were sent, but some failed.';
                    if (res.failed && res.failed.length) {
                        partialMessage += '<br><small>' + (res.failed[0].message || 'Unknown failure') + '</small>';
                    }
                    toastr.warning(partialMessage);
                    setTimeout(function(){ window.location.reload(); }, 1500);
               } else {
                    var errorMessage = res.message || 'Failed something wrong';
                    if (res.failed && res.failed.length && res.failed[0].message) {
                        errorMessage = res.failed[0].message;
                    }
                    toastr.error(errorMessage);
               }
           },
           error: function(){
               toastr.error('Something went wrong');
           }
        });
    });

    $(document).on('click', '.block-ip-btn', function(e){
        e.preventDefault();
        var $btn = $(this);
        var ip = $btn.data('ip');
        var reason = $btn.data('reason') || 'Fake order';
        if(!ip){
            toastr.error('IP address not found');
            return;
        }
        $.ajax({
            url: "{{ route('customers.ipblock.quick') }}",
            type: "POST",
            data: { _token: "{{ csrf_token() }}", ip: ip, reason: reason },
            success: function(res){
                if(res.status === 'success'){
                    toastr.success(res.message || 'IP blocked successfully');
                    $btn.replaceWith('<span class="badge bg-secondary"><i class="fe-shield"></i> Blocked</span>');
                } else {
                    toastr.error(res.message || 'Failed to block IP');
                }
            },
            error: function(xhr){
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to block IP');
            }
        });
    });

    $(document).on('click', '[data-bs-target="#pathao"]', function(e){
        var order_ids = $('input.checkbox:checked').map(function(){ return $(this).val(); }).get();
        if(order_ids.length == 0){
            toastr.error('Please Select Atleast One Order First!');
            e.preventDefault();
            return false;
        }
        $('#pathao_order_ids').val(order_ids.join(','));
    });

    $(document).on('change', '#pathao_manual_toggle', function(){
        var isChecked = $(this).is(':checked');
        $('#pathao_manual_fields').stop(true, true).slideToggle(isChecked);
        if(!isChecked){
            $('#pathaocity').val('').trigger('change');
            $('#pathaozone').html('<option value="">Select Zone...</option>').trigger('chosen:updated');
            $('#pathaoarea').html('<option value="">Select Area...</option>').trigger('chosen:updated');
        }
    });

    $(document).on('change', '#pathaocity', function(){
        var cityId = $(this).val();
        $('#pathaozone').html('<option value="">Loading...</option>').trigger('chosen:updated');
        $('#pathaoarea').html('<option value="">Select Area...</option>').trigger('chosen:updated');
        if(!cityId){
            $('#pathaozone').html('<option value="">Select Zone...</option>').trigger('chosen:updated');
            return;
        }
        $.ajax({
            url: "{{ route('pathaocity') }}",
            type: "GET",
            data: { city_id: cityId },
            success: function(res){
                var options = '<option value="">Select Zone...</option>';
                if(res && res.data && res.data.data && res.data.data.length > 0){
                    $.each(res.data.data, function(key, zone){
                        options += '<option value="' + zone.zone_id + '">' + zone.zone_name + '</option>';
                    });
                }
                $('#pathaozone').html(options).trigger('chosen:updated');
            },
            error: function(){
                $('#pathaozone').html('<option value="">Select Zone...</option>').trigger('chosen:updated');
                toastr.error('Could not load Pathao zones');
            }
        });
    });

    $(document).on('change', '#pathaozone', function(){
        var zoneId = $(this).val();
        $('#pathaoarea').html('<option value="">Loading...</option>').trigger('chosen:updated');
        if(!zoneId){
            $('#pathaoarea').html('<option value="">Select Area...</option>').trigger('chosen:updated');
            return;
        }
        $.ajax({
            url: "{{ route('pathaozone') }}",
            type: "GET",
            data: { zone_id: zoneId },
            success: function(res){
                var options = '<option value="">Select Area...</option>';
                if(res && res.data && res.data.data && res.data.data.length > 0){
                    $.each(res.data.data, function(key, area){
                        options += '<option value="' + area.area_id + '">' + area.area_name + '</option>';
                    });
                }
                $('#pathaoarea').html(options).trigger('chosen:updated');
            },
            error: function(){
                $('#pathaoarea').html('<option value="">Select Area...</option>').trigger('chosen:updated');
                toastr.error('Could not load Pathao areas');
            }
        });
    });

    $(document).on('submit', '#order_sendto_pathao', function(e){
        e.preventDefault();
        var $form = $(this);
        var $submitButton = $form.find('button[type="submit"]');
        var orderIds = $('#pathao_order_ids').val();
        if(!orderIds){
            toastr.error('Please select orders first');
            return;
        }
        if(!$('#pathaostore').val()){
            toastr.error('Please select a store');
            return;
        }
        if($('#pathao_manual_toggle').is(':checked') && (!$('#pathaocity').val() || !$('#pathaozone').val() || !$('#pathaoarea').val())){
            toastr.error('Please select city, zone and area');
            return;
        }
        $submitButton.prop('disabled', true).text('Sending...');
        $.ajax({
            url: $form.attr('action'),
            type: "POST",
            data: $form.serialize(),
            success: function(res){
                if(res.status === 'success'){
                    var successCount = res.result && res.result.success ? res.result.success.length : 0;
                    var failedCount = res.result && res.result.failed ? res.result.failed.length : 0;
                    if(successCount > 0){
                        toastr.success(successCount + ' order sent to Pathao successfully');
                    }
                    if(failedCount > 0){
                        var firstError = res.result.failed[0] && (res.result.failed[0].message || res.result.failed[0].body) ? (res.result.failed[0].message || res.result.failed[0].body) : 'Please check address/area.';
                        toastr.warning(failedCount + ' order could not be sent. ' + firstError);
                    }
                    if(successCount > 0){
                        $('#pathao').modal('hide');
                        setTimeout(function(){ window.location.reload(); }, 1500);
                    }
                } else {
                    toastr.error(res.message || 'Failed to send orders');
                }
            },
            error: function(xhr){
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to send orders');
            },
            complete: function(){
                $submitButton.prop('disabled', false).text('Send To Pathao');
            }
        });
    });

    function syncOrderFilterEmptyState() {
        var visibleGroups = $('#ordersFilterPanel [data-filter-group].is-visible').length;
        $('#ordersFilterEmpty').toggleClass('is-visible', visibleGroups === 0);
    }

    function syncSelectedOrderCount() {
        var selectedCount = $('input.checkbox:checked').length;
        $('#ordersSelectionCountValue').text(selectedCount);
    }

    $(document).on('click', '.orders-filter-item[data-filter-target]', function(e){
        e.preventDefault();
        var target = $(this).data('filter-target');
        var $panel = $('#ordersFilterPanel');
        var $group = $panel.find('[data-filter-group="' + target + '"]');

        $panel.addClass('is-open');
        $group.addClass('is-visible');
        syncOrderFilterEmptyState();
    });

    $(document).on('click', '.order-page-reload-btn', function(){
        window.location.reload();
    });

    $(document).on('change', '.checkall, .checkbox', function(){
        setTimeout(syncSelectedOrderCount, 0);
    });

    syncSelectedOrderCount();
    syncOrderFilterEmptyState();

    $(document).on('keydown', function(e){
        if (e.ctrlKey && e.key === '/') {
            e.preventDefault();
            $('.orders-search-input').first().trigger('focus').select();
        }
    });

});
</script>
@endsection


