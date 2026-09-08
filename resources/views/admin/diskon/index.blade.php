@extends('admin.template')

@section('content')
<style>
    .discount-header {
        background: white;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        margin-bottom: 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }

    .discount-title h2 {
        font-size: 26px;
        color: #1e3c72;
        font-weight: 700;
        margin-bottom: 5px;
    }

    .discount-title p {
        font-size: 14px;
        color: #64748b;
    }

    .discount-actions {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }

    .btn {
        padding: 12px 24px;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        border: none;
        cursor: pointer;
        display: none;
        align-items: center;
        gap: 8px;
        transition: all 0.3s;
        text-decoration: none;
    }

    .btn.show {
        display: inline-flex;
    }

    .btn-primary {
        background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        color: white;
        display: inline-flex;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(30, 60, 114, 0.3);
    }

    .btn svg {
        width: 18px;
        height: 18px;
        fill: currentColor;
    }

    .btn-danger {
        background: #ef4444;
        color: white;
    }

    .btn-danger:hover {
        background: #dc2626;
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(239, 68, 68, 0.3);
    }

    .btn-warning {
        background: #f59e0b;
        color: white;
    }

    .btn-warning:hover {
        background: #d97706;
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(245, 158, 11, 0.3);
    }

    .discount-table-container {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        overflow: hidden;
    }

    .table-controls {
        padding: 20px 25px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }

    .search-box {
        position: relative;
        flex: 1;
        min-width: 250px;
        max-width: 400px;
    }

    .search-box input {
        width: 100%;
        padding: 12px 45px 12px 15px;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        font-size: 14px;
        outline: none;
        transition: all 0.3s;
    }

    .search-box input:focus {
        border-color: #1e3c72;
        box-shadow: 0 0 0 3px rgba(30, 60, 114, 0.1);
    }

    .search-box svg {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        width: 18px;
        height: 18px;
        fill: #94a3b8;
    }

    .filter-group {
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .filter-select {
        padding: 10px 15px;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        font-size: 14px;
        outline: none;
        cursor: pointer;
        background: white;
        transition: all 0.3s;
    }

    .filter-select:focus {
        border-color: #1e3c72;
    }

    .discount-table {
        width: 100%;
        border-collapse: collapse;
    }

    .discount-table thead {
        background: #f8fafc;
    }

    .discount-table thead th {
        padding: 16px 20px;
        text-align: left;
        font-size: 13px;
        font-weight: 700;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #e2e8f0;
    }

    .discount-table thead th:first-child {
        width: 50px;
    }

    .discount-table tbody td {
        padding: 18px 20px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 14px;
        color: #334155;
    }

    .discount-table tbody tr:hover {
        background: #f8fafc;
    }

    .discount-table tbody tr.selected {
        background: #eff6ff;
    }

    .checkbox-wrapper {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .checkbox-wrapper input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
        accent-color: #1e3c72;
    }

    .product-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .product-image {
        width: 50px;
        height: 50px;
        background: #f1f5f9;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .product-image svg {
        width: 24px;
        height: 24px;
        fill: #94a3b8;
    }

    .product-details h4 {
        font-size: 15px;
        font-weight: 600;
        color: #1e3c72;
        margin-bottom: 3px;
    }

    .product-details p {
        font-size: 13px;
        color: #64748b;
    }

    .discount-badge {
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 700;
        display: inline-block;
    }

    .discount-badge.percent {
        background: #dcfce7;
        color: #16a34a;
    }

    .discount-badge.nominal {
        background: #dbeafe;
        color: #2563eb;
    }

    .toggle-switch {
        position: relative;
        width: 50px;
        height: 26px;
        background: #e2e8f0;
        border-radius: 30px;
        cursor: pointer;
        transition: all 0.3s;
    }

    .toggle-switch.active {
        background: #16a34a;
    }

    .toggle-switch .toggle-slider {
        position: absolute;
        top: 3px;
        left: 3px;
        width: 20px;
        height: 20px;
        background: white;
        border-radius: 50%;
        transition: all 0.3s;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }

    .toggle-switch.active .toggle-slider {
        transform: translateX(24px);
    }

    .action-buttons {
        display: flex;
        gap: 8px;
    }

    .btn-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        border: none;
        transition: all 0.3s;
    }

    .btn-icon svg {
        width: 16px;
        height: 16px;
    }

    .btn-edit {
        background: #fef3c7;
        color: #d97706;
    }

    .btn-edit:hover {
        background: #fcd34d;
    }

    .btn-delete {
        background: #fee2e2;
        color: #dc2626;
    }

    .btn-delete:hover {
        background: #fecaca;
    }

    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 9999;
        align-items: center;
        justify-content: center;
        padding: 20px;
        animation: fadeIn 0.3s;
        overflow-y: auto;
    }

    .modal.show {
        display: flex;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    .modal-content {
        background: white;
        border-radius: 16px;
        width: 100%;
        max-width: 600px;
        max-height: 85vh;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        animation: slideUp 0.3s;
        position: relative;
    }

    @keyframes slideUp {
        from { transform: translateY(30px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }

    .modal-header {
        padding: 25px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: white;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .modal-header h3 {
        font-size: 20px;
        font-weight: 700;
        color: #1e3c72;
    }

    .modal-close {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: #f1f5f9;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s;
    }

    .modal-close:hover {
        background: #e2e8f0;
    }

    .modal-close svg {
        width: 18px;
        height: 18px;
        fill: #64748b;
    }

    .modal-body {
        padding: 25px;
        overflow-y: auto;
        flex: 1;
        max-height: calc(85vh - 140px);
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-size: 14px;
        font-weight: 600;
        color: #334155;
    }

    .form-group label .required {
        color: #ef4444;
    }

    .form-input {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        font-size: 14px;
        outline: none;
        transition: all 0.3s;
    }

    .form-input:focus {
        border-color: #1e3c72;
        box-shadow: 0 0 0 3px rgba(30, 60, 114, 0.1);
    }

    .form-select {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        font-size: 14px;
        outline: none;
        cursor: pointer;
        background: white;
        transition: all 0.3s;
    }

    .form-select:focus {
        border-color: #1e3c72;
        box-shadow: 0 0 0 3px rgba(30, 60, 114, 0.1);
    }

    .search-product-box {
        position: relative;
    }

    .search-product-input {
        width: 100%;
        padding: 12px 45px 12px 15px;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        font-size: 14px;
        outline: none;
        transition: all 0.3s;
    }

    .search-product-input:focus {
        border-color: #1e3c72;
        box-shadow: 0 0 0 3px rgba(30, 60, 114, 0.1);
    }

    .search-product-box svg {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        width: 18px;
        height: 18px;
        fill: #94a3b8;
        pointer-events: none;
    }

    .product-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 2px solid #e2e8f0;
        border-top: none;
        border-radius: 0 0 10px 10px;
        max-height: 200px;
        overflow-y: auto;
        z-index: 1000;
        display: none;
        box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    }

    .product-dropdown.show {
        display: block;
    }

    .product-item {
        padding: 12px 15px;
        cursor: pointer;
        transition: all 0.2s;
        border-bottom: 1px solid #f1f5f9;
    }

    .product-item:hover {
        background: #f8fafc;
    }

    .product-item:last-child {
        border-bottom: none;
    }

    .product-item-name {
        font-size: 14px;
        font-weight: 600;
        color: #1e3c72;
        margin-bottom: 3px;
    }

    .product-item-code {
        font-size: 12px;
        color: #64748b;
    }

    .selected-product-tag {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        background: #eff6ff;
        border: 2px solid #bfdbfe;
        border-radius: 8px;
        font-size: 13px;
        color: #1e40af;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
    }

    .selected-product-tag:hover {
        background: #dbeafe;
        border-color: #93c5fd;
    }

    .selected-product-tag svg {
        width: 14px;
        height: 14px;
        fill: currentColor;
    }

    .discount-type-info {
        padding: 12px 15px;
        background: #f0f9ff;
        border-radius: 10px;
        font-size: 13px;
        color: #0369a1;
        margin-top: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .discount-type-info svg {
        width: 16px;
        height: 16px;
        fill: currentColor;
        flex-shrink: 0;
    }

    .modal-footer {
        padding: 20px 25px;
        border-top: 1px solid #e2e8f0;
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        background: white;
        position: sticky;
        bottom: 0;
        z-index: 10;
    }

    .btn-cancel {
        padding: 12px 24px;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        border: 2px solid #e2e8f0;
        background: white;
        color: #64748b;
        cursor: pointer;
        transition: all 0.3s;
    }

    .btn-cancel:hover {
        border-color: #cbd5e1;
        background: #f8fafc;
    }

    .btn-submit {
        padding: 12px 24px;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        border: none;
        background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        color: white;
        cursor: pointer;
        transition: all 0.3s;
    }

    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(30, 60, 114, 0.3);
    }

    .empty-state {
        padding: 60px 20px;
        text-align: center;
    }

    .empty-state svg {
        width: 80px;
        height: 80px;
        fill: #cbd5e1;
        margin-bottom: 20px;
    }

    .empty-state h3 {
        font-size: 18px;
        color: #64748b;
        margin-bottom: 10px;
    }

    .empty-state p {
        font-size: 14px;
        color: #94a3b8;
    }

    @media (max-width: 768px) {
        .discount-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .discount-actions {
            width: 100%;
            flex-direction: column;
        }

        .btn {
            width: 100%;
            justify-content: center;
        }

        .table-controls {
            flex-direction: column;
            align-items: stretch;
        }

        .search-box {
            max-width: 100%;
        }

        .filter-group {
            flex-direction: column;
            width: 100%;
        }

        .filter-select {
            width: 100%;
        }

        .discount-table-container {
            overflow-x: auto;
        }

        .discount-table {
            min-width: 800px;
        }

        .modal-content {
            max-width: 100%;
            margin: 0;
            max-height: 100vh;
            border-radius: 0;
        }
    }
</style>

<div class="discount-header">
    <div class="discount-title">
        <h2>Manajemen Diskon</h2>
        <p>Kelola diskon produk dengan mudah dan efisien</p>
    </div>
    <div class="discount-actions">
        <button class="btn btn-warning" id="btnEditMass">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
            </svg>
            Edit Terpilih
        </button>
        <button class="btn btn-danger" id="btnDeleteMass">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
            </svg>
            Hapus Terpilih
        </button>
        <button class="btn btn-primary" id="btnAddDiscount">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
            </svg>
            Tambah Diskon
        </button>
    </div>
</div>

<div class="discount-table-container">
    <div class="table-controls">
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="Cari nama produk atau kode...">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
            </svg>
        </div>
        <div class="filter-group">
            <select class="filter-select" id="filterType">
                <option value="">Semua Tipe</option>
                <option value="percent">Persen (%)</option>
                <option value="nominal">Nominal (Rp)</option>
            </select>
            <select class="filter-select" id="filterStatus">
                <option value="">Semua Status</option>
                <option value="1">Aktif</option>
                <option value="0">Nonaktif</option>
            </select>
        </div>
    </div>

    <table class="discount-table">
        <thead>
            <tr>
                <th>
                    <div class="checkbox-wrapper">
                        <input type="checkbox" id="selectAll">
                    </div>
                </th>
                <th>Produk</th>
                <th>Diskon</th>
                <th>Tipe</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody id="discountTableBody">
            @forelse($diskons as $diskon)
            <tr data-id="{{ $diskon->id }}" data-aktif="{{ $diskon->aktif }}">
                <td>
                    <div class="checkbox-wrapper">
                        <input type="checkbox" class="row-checkbox" value="{{ $diskon->id }}">
                    </div>
                </td>
                <td>
                    <div class="product-info">
                        <div class="product-image">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path d="M20 6h-2.18c.11-.31.18-.65.18-1 0-1.66-1.34-3-3-3-1.05 0-1.96.54-2.5 1.35l-.5.67-.5-.68C10.96 2.54 10.05 2 9 2 7.34 2 6 3.34 6 5c0 .35.07.69.18 1H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-5-2c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zM9 4c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm11 15H4v-2h16v2zm0-5H4V8h5.08L7 10.83 8.62 12 11 8.76l1-1.36 1 1.36L15.38 12 17 10.83 14.92 8H20v6z"/>
                            </svg>
                        </div>
                        <div class="product-details">
                            <h4>{{ $diskon->barang->nama_barang }}</h4>
                            <p>{{ $diskon->barang->kode_barang }}</p>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="discount-badge {{ $diskon->nilai > 100 ? 'nominal' : 'percent' }}">
                        {{ $diskon->nilai > 100 ? 'Rp ' . number_format($diskon->nilai, 0, ',', '.') : $diskon->nilai . '%' }}
                    </span>
                </td>
                <td>{{ $diskon->nilai > 100 ? 'Nominal' : 'Persen' }}</td>
                <td>
                    <div class="toggle-switch {{ $diskon->aktif == 1 ? 'active' : '' }}" onclick="toggleStatus({{ $diskon->id }}, {{ $diskon->aktif }})">
                        <div class="toggle-slider"></div>
                    </div>
                </td>
                <td>
                    <div class="action-buttons">
                        <button class="btn-icon btn-edit" onclick="editDiscount({{ $diskon->id }}, {{ $diskon->barang_id }}, '{{ $diskon->barang->nama_barang }}', '{{ $diskon->nilai }}', '{{ $diskon->aktif }}')">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                            </svg>
                        </button>
                        <button class="btn-icon btn-delete" onclick="deleteDiscount({{ $diskon->id }})">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                            </svg>
                        </button>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6">
                    <div class="empty-state">
                        <h3>Belum Ada Diskon</h3>
                        <p>Mulai tambahkan diskon untuk produk Anda</p>
                    </div>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
<div style="padding: 20px;">
    {{ $diskons->links() }}
</div>

<div class="modal" id="discountModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Tambah Diskon</h3>
            <button class="modal-close" onclick="closeModal()">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                </svg>
            </button>
        </div>
        <form id="discountForm" action="{{ url('admin/diskon/store') }}" method="POST">
            @csrf
            <input type="hidden" name="id" id="discountId">
            <div class="modal-body">
                <div class="form-group">
                    <label>Pilih Produk <span class="required">*</span></label>
                    <div class="search-product-box">
                        <input type="text" class="search-product-input" id="productSearch" placeholder="Cari nama atau kode produk..." autocomplete="off">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                        </svg>
                        <div class="product-dropdown" id="productDropdown"></div>
                    </div>
                    <div id="selectedProductsList" style="margin-top: 15px; display: none;">
                        <div style="font-size: 13px; color: #64748b; margin-bottom: 10px; font-weight: 600;">
                            Produk Dipilih (<span id="selectedProductCount">0</span>):
                        </div>
                        <div id="selectedProductsContainer" style="display: flex; flex-wrap: wrap; gap: 8px;"></div>
                    </div>
                    <input type="hidden" name="barang_ids" id="selectedProductIds">
                </div>

                <div class="form-group">
                    <label>Nilai Diskon <span class="required">*</span></label>
                    <input type="number" class="form-input" name="nilai" id="discountValue" placeholder="Contoh: 10 atau 50000" required step="0.01" min="0">
                    <div class="discount-type-info" id="discountTypeInfo">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path d="M11 7h2v2h-2zm0 4h2v6h-2zm1-9C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/>
                        </svg>
                        <span id="discountTypeText">Masukkan nilai 1-100 untuk diskon persen (%), atau nilai > 100 untuk diskon nominal (Rp)</span>
                    </div>
                </div>

                <div class="form-group">
                    <label>Status <span class="required">*</span></label>
                    <select class="form-select" name="aktif" id="discountStatus" required>
                        <option value="1">Aktif</option>
                        <option value="0">Nonaktif</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal()">Batal</button>
                <button type="submit" class="btn-submit">Simpan Diskon</button>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="massEditModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Diskon Massal</h3>
            <button class="modal-close" onclick="closeMassEditModal()">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>Nilai Diskon <span class="required">*</span></label>
                <input type="number" class="form-input" id="massDiscountValue" placeholder="Contoh: 10 atau 50000" required step="0.01" min="0">
                <div class="discount-type-info" id="massDiscountTypeInfo">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="M11 7h2v2h-2zm0 4h2v6h-2zm1-9C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/>
                    </svg>
                    <span id="massDiscountTypeText">Masukkan nilai 1-100 untuk diskon persen (%), atau nilai > 100 untuk diskon nominal (Rp)</span>
                </div>
            </div>

            <div class="form-group">
                <label>Status <span class="required">*</span></label>
                <select class="form-select" id="massDiscountStatus" required>
                    <option value="1">Aktif</option>
                    <option value="0">Nonaktif</option>
                </select>
            </div>

            <div style="background: #f0f9ff; padding: 15px; border-radius: 10px; font-size: 13px; color: #0369a1; margin-top: 15px;">
                <strong><span id="selectedCount">0</span> diskon</strong> akan diubah dengan nilai yang sama
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-cancel" onclick="closeMassEditModal()">Batal</button>
            <button type="button" class="btn-submit" onclick="submitMassEdit()">Simpan Perubahan</button>
        </div>
    </div>
</div>

<script>
    let selectedIds = [];
    let selectedProducts = [];
    let isEditMode = false;

    document.getElementById('btnAddDiscount').addEventListener('click', function() {
        isEditMode = false;
        document.getElementById('modalTitle').textContent = 'Tambah Diskon';
        document.getElementById('discountForm').action = '{{ url("admin/diskon/store") }}';
        document.getElementById('discountForm').reset();
        document.getElementById('discountId').value = '';
        document.getElementById('productSearch').value = '';
        selectedProducts = [];
        updateSelectedProductsDisplay();
        document.getElementById('discountModal').classList.add('show');
    });

    document.getElementById('selectAll').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.row-checkbox');
        checkboxes.forEach(cb => {
            cb.checked = this.checked;
            if(this.checked) {
                cb.closest('tr').classList.add('selected');
            } else {
                cb.closest('tr').classList.remove('selected');
            }
        });
        updateSelectedIds();
        toggleMassActions();
    });

    document.querySelectorAll('.row-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            if(this.checked) {
                this.closest('tr').classList.add('selected');
            } else {
                this.closest('tr').classList.remove('selected');
            }
            updateSelectedIds();
            toggleMassActions();
            updateSelectAll();
        });
    });

    function updateSelectedIds() {
        selectedIds = Array.from(document.querySelectorAll('.row-checkbox:checked')).map(cb => cb.value);
    }

    function toggleMassActions() {
        const btnEditMass = document.getElementById('btnEditMass');
        const btnDeleteMass = document.getElementById('btnDeleteMass');
        
        if(selectedIds.length > 0) {
            btnEditMass.classList.add('show');
            btnDeleteMass.classList.add('show');
        } else {
            btnEditMass.classList.remove('show');
            btnDeleteMass.classList.remove('show');
        }
    }

    function updateSelectAll() {
        const allCheckboxes = document.querySelectorAll('.row-checkbox');
        const checkedCheckboxes = document.querySelectorAll('.row-checkbox:checked');
        const selectAll = document.getElementById('selectAll');
        
        if(allCheckboxes.length === checkedCheckboxes.length && allCheckboxes.length > 0) {
            selectAll.checked = true;
        } else {
            selectAll.checked = false;
        }
    }

    document.getElementById('btnEditMass').addEventListener('click', function() {
        if(selectedIds.length === 0) {
            alert('Pilih minimal 1 diskon untuk diedit');
            return;
        }
        
        document.getElementById('selectedCount').textContent = selectedIds.length;
        document.getElementById('massDiscountValue').value = '';
        document.getElementById('massDiscountStatus').value = '1';
        document.getElementById('massEditModal').classList.add('show');
    });

    function closeMassEditModal() {
        document.getElementById('massEditModal').classList.remove('show');
    }

    function submitMassEdit() {
        const nilai = document.getElementById('massDiscountValue').value;
        const aktif = document.getElementById('massDiscountStatus').value;

        if(!nilai || nilai <= 0) {
            alert('Nilai diskon harus diisi dan lebih dari 0');
            return;
        }

        const formData = new FormData();
        formData.append('_token', '{{ csrf_token() }}');
        formData.append('ids', JSON.stringify(selectedIds));
        formData.append('nilai', nilai);
        formData.append('aktif', aktif);

        fetch('{{ url("admin/diskon/update-mass") }}', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                alert('Berhasil mengupdate ' + selectedIds.length + ' diskon');
                location.reload();
            } else {
                alert('Gagal mengupdate diskon');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan');
        });
    }

    document.getElementById('massDiscountValue').addEventListener('input', function() {
        const value = parseFloat(this.value) || 0;
        const typeInfo = document.getElementById('massDiscountTypeInfo');
        const typeText = document.getElementById('massDiscountTypeText');
        
        if(value === 0) {
            typeInfo.style.background = '#f0f9ff';
            typeInfo.style.color = '#0369a1';
            typeText.textContent = 'Masukkan nilai 1-100 untuk diskon persen (%), atau nilai > 100 untuk diskon nominal (Rp)';
        } else if(value > 0 && value <= 100) {
            typeInfo.style.background = '#dcfce7';
            typeInfo.style.color = '#16a34a';
            typeText.textContent = 'Diskon Persen: ' + value + '%';
        } else if(value > 100) {
            typeInfo.style.background = '#dbeafe';
            typeInfo.style.color = '#2563eb';
            typeText.textContent = 'Diskon Nominal: Rp ' + value.toLocaleString('id-ID');
        }
    });

    document.getElementById('btnDeleteMass').addEventListener('click', function() {
        if(selectedIds.length === 0) {
            alert('Pilih minimal 1 diskon untuk dihapus');
            return;
        }
        
        if(!confirm('Yakin ingin menghapus ' + selectedIds.length + ' diskon?')) {
            return;
        }

        const formData = new FormData();
        formData.append('_token', '{{ csrf_token() }}');
        formData.append('ids', JSON.stringify(selectedIds));

        fetch('{{ url("admin/diskon/delete-mass") }}', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                alert('Berhasil menghapus ' + selectedIds.length + ' diskon');
                location.reload();
            } else {
                alert('Gagal menghapus diskon');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan');
        });
    });

    document.getElementById('productSearch').addEventListener('input', function() {
        const keyword = this.value.toLowerCase();
        const dropdown = document.getElementById('productDropdown');
        
        if(keyword.length < 2) {
            dropdown.classList.remove('show');
            return;
        }

        fetch('{{ url("admin/diskon/search-products") }}?keyword=' + keyword)
        .then(response => response.json())
        .then(data => {
            if(data.length === 0) {
                dropdown.innerHTML = '<div style="padding: 15px; text-align: center; color: #94a3b8; font-size: 13px;">Produk tidak ditemukan</div>';
            } else {
                dropdown.innerHTML = data.map(product => `
                    <div class="product-item" onclick="selectProduct(${product.id}, '${product.nama_barang}', '${product.kode_barang}')">
                        <div class="product-item-name">${product.nama_barang}</div>
                        <div class="product-item-code">${product.kode_barang}</div>
                    </div>
                `).join('');
            }
            dropdown.classList.add('show');
        })
        .catch(error => {
            console.error('Error:', error);
        });
    });

    function selectProduct(id, name, code) {
        if(isEditMode) {
            selectedProducts = [{id: id, name: name, code: code}];
        } else {
            const exists = selectedProducts.find(p => p.id === id);
            if(!exists) {
                selectedProducts.push({id: id, name: name, code: code});
            }
        }
        
        document.getElementById('productSearch').value = '';
        document.getElementById('productDropdown').classList.remove('show');
        updateSelectedProductsDisplay();
    }

    function removeProduct(id) {
        selectedProducts = selectedProducts.filter(p => p.id !== id);
        updateSelectedProductsDisplay();
    }

    function updateSelectedProductsDisplay() {
        const container = document.getElementById('selectedProductsContainer');
        const listDiv = document.getElementById('selectedProductsList');
        const countSpan = document.getElementById('selectedProductCount');
        
        if(selectedProducts.length === 0) {
            listDiv.style.display = 'none';
            document.getElementById('selectedProductIds').value = '';
            return;
        }
        
        listDiv.style.display = 'block';
        countSpan.textContent = selectedProducts.length;
        
        container.innerHTML = selectedProducts.map(product => `
            <div class="selected-product-tag" onclick="removeProduct(${product.id})">
                <span>${product.name}</span>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                </svg>
            </div>
        `).join('');
        
        document.getElementById('selectedProductIds').value = JSON.stringify(selectedProducts.map(p => p.id));
    }

    document.addEventListener('click', function(e) {
        if(!e.target.closest('.search-product-box')) {
            document.getElementById('productDropdown').classList.remove('show');
        }
    });

    document.getElementById('discountValue').addEventListener('input', function() {
        const value = parseFloat(this.value) || 0;
        const typeInfo = document.getElementById('discountTypeInfo');
        const typeText = document.getElementById('discountTypeText');
        
        if(value === 0) {
            typeInfo.style.background = '#f0f9ff';
            typeInfo.style.color = '#0369a1';
            typeText.textContent = 'Masukkan nilai 1-100 untuk diskon persen (%), atau nilai > 100 untuk diskon nominal (Rp)';
        } else if(value > 0 && value <= 100) {
            typeInfo.style.background = '#dcfce7';
            typeInfo.style.color = '#16a34a';
            typeText.textContent = 'Diskon Persen: ' + value + '%';
        } else if(value > 100) {
            typeInfo.style.background = '#dbeafe';
            typeInfo.style.color = '#2563eb';
            typeText.textContent = 'Diskon Nominal: Rp ' + value.toLocaleString('id-ID');
        }
    });

    document.getElementById('discountForm').addEventListener('submit', function(e) {
        if(!isEditMode && selectedProducts.length === 0) {
            e.preventDefault();
            alert('Pilih minimal 1 produk');
            return false;
        }
    });

    function editDiscount(id, barangId, productName, value, aktif) {
        isEditMode = true;
        document.getElementById('modalTitle').textContent = 'Edit Diskon';
        document.getElementById('discountForm').action = '{{ url("admin/diskon/update") }}/' + id;
        document.getElementById('discountId').value = id;
        selectedProducts = [{id: barangId, name: productName, code: ''}];
        updateSelectedProductsDisplay();
        document.getElementById('discountValue').value = value;
        document.getElementById('discountStatus').value = aktif;
        
        const event = new Event('input');
        document.getElementById('discountValue').dispatchEvent(event);
        
        document.getElementById('discountModal').classList.add('show');
    }

    function deleteDiscount(id) {
        if(!confirm('Yakin ingin menghapus diskon ini?')) {
            return;
        }

        fetch('{{ url("admin/diskon/delete") }}/' + id, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                alert('Diskon berhasil dihapus');
                location.reload();
            } else {
                alert('Gagal menghapus diskon');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan');
        });
    }

    function toggleStatus(id, currentAktif) {
        const newAktif = currentAktif == 1 ? 0 : 1;
        
        const formData = new FormData();
        formData.append('_token', '{{ csrf_token() }}');
        formData.append('aktif', newAktif);

        fetch('{{ url("admin/diskon/toggle-status") }}/' + id, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                location.reload();
            } else {
                alert('Gagal mengubah status');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan');
        });
    }

    function closeModal() {
        document.getElementById('discountModal').classList.remove('show');
    }

    document.getElementById('searchInput').addEventListener('input', function() {
        const keyword = this.value.toLowerCase();
        const rows = document.querySelectorAll('#discountTableBody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            if(text.includes(keyword)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });

    document.getElementById('filterType').addEventListener('change', filterTable);
    document.getElementById('filterStatus').addEventListener('change', filterTable);

    function filterTable() {
        const typeFilter = document.getElementById('filterType').value;
        const statusFilter = document.getElementById('filterStatus').value;
        const rows = document.querySelectorAll('#discountTableBody tr');
        
        rows.forEach(row => {
            let showRow = true;
            
            if(typeFilter) {
                const discountBadge = row.querySelector('.discount-badge');
                if(discountBadge) {
                    if(typeFilter === 'percent' && !discountBadge.classList.contains('percent')) {
                        showRow = false;
                    }
                    if(typeFilter === 'nominal' && !discountBadge.classList.contains('nominal')) {
                        showRow = false;
                    }
                }
            }
            
            if(statusFilter && showRow) {
                const aktif = row.getAttribute('data-aktif');
                if(statusFilter !== aktif) {
                    showRow = false;
                }
            }
            
            row.style.display = showRow ? '' : 'none';
        });
    }

    document.getElementById('discountModal').addEventListener('click', function(e) {
        if(e.target === this) {
            closeModal();
        }
    });

    document.getElementById('massEditModal').addEventListener('click', function(e) {
        if(e.target === this) {
            closeMassEditModal();
        }
    });
</script>
@endsection