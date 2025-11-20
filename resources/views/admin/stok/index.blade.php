@extends('admin.template')

@section('content')
<style>
    .stok-header {
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

    .stok-title h2 {
        font-size: 26px;
        color: #1e3c72;
        font-weight: 700;
        margin-bottom: 5px;
    }

    .stok-title p {
        font-size: 14px;
        color: #64748b;
    }

    .stok-actions {
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
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s;
        text-decoration: none;
    }

    .btn-primary {
        background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        color: white;
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

    .stok-table-container {
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

    .stok-table {
        width: 100%;
        border-collapse: collapse;
    }

    .stok-table thead {
        background: #f8fafc;
    }

    .stok-table thead th {
        padding: 16px 20px;
        text-align: left;
        font-size: 13px;
        font-weight: 700;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #e2e8f0;
    }

    .stok-table tbody td {
        padding: 18px 20px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 14px;
        color: #334155;
    }

    .stok-table tbody tr:hover {
        background: #f8fafc;
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

    .stok-badge {
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 700;
        display: inline-block;
    }

    .stok-badge.high {
        background: #dcfce7;
        color: #16a34a;
    }

    .stok-badge.medium {
        background: #fef3c7;
        color: #d97706;
    }

    .stok-badge.low {
        background: #fee2e2;
        color: #dc2626;
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
        max-width: 700px;
        max-height: 85vh;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        animation: slideUp 0.3s;
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
        max-height: 250px;
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

    .product-item-stok {
        font-size: 12px;
        color: #16a34a;
        font-weight: 600;
        margin-top: 3px;
    }

    .selected-products-list {
        margin-top: 20px;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        overflow: hidden;
    }

    .selected-products-header {
        padding: 12px 15px;
        background: #f8fafc;
        border-bottom: 2px solid #e2e8f0;
        font-size: 13px;
        font-weight: 600;
        color: #64748b;
    }

    .selected-product-row {
        padding: 15px;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .selected-product-row:last-child {
        border-bottom: none;
    }

    .selected-product-info {
        flex: 1;
    }

    .selected-product-name {
        font-size: 14px;
        font-weight: 600;
        color: #1e3c72;
        margin-bottom: 3px;
    }

    .selected-product-current {
        font-size: 12px;
        color: #64748b;
    }

    .selected-product-current span {
        color: #16a34a;
        font-weight: 600;
    }

    .stok-input-group {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .stok-input {
        width: 120px;
        padding: 10px 15px;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        font-size: 14px;
        outline: none;
        transition: all 0.3s;
        text-align: center;
        font-weight: 600;
    }

    .stok-input:focus {
        border-color: #1e3c72;
        box-shadow: 0 0 0 3px rgba(30, 60, 114, 0.1);
    }

    .btn-remove {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: #fee2e2;
        color: #dc2626;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s;
    }

    .btn-remove:hover {
        background: #fecaca;
    }

    .btn-remove svg {
        width: 16px;
        height: 16px;
        fill: currentColor;
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
        .stok-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .stok-actions {
            width: 100%;
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

        .stok-table-container {
            overflow-x: auto;
        }

        .stok-table {
            min-width: 600px;
        }

        .modal-content {
            max-width: 100%;
            margin: 0;
            max-height: 100vh;
            border-radius: 0;
        }

        .selected-product-row {
            flex-direction: column;
            align-items: stretch;
        }

        .stok-input-group {
            justify-content: space-between;
        }
    }
</style>

<div class="stok-header">
    <div class="stok-title">
        <h2>Manajemen Stok</h2>
        <p>Kelola stok produk dengan mudah dan efisien</p>
    </div>
    <div class="stok-actions">
        <button class="btn btn-primary" id="btnAddStok">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
            </svg>
            Tambah Stok
        </button>
    </div>
</div>

<div class="stok-table-container">
    <div class="table-controls">
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="Cari nama produk atau kode...">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
            </svg>
        </div>
    </div>

    <table class="stok-table">
        <thead>
            <tr>
                <th>Produk</th>
                <th>Stok Saat Ini</th>
                <th>Terakhir Update</th>
            </tr>
        </thead>
        <tbody id="stokTableBody">
            @forelse($barang as $item)
            <tr>
                <td>
                    <div class="product-info">
                        <div class="product-image">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path d="M20 6h-2.18c.11-.31.18-.65.18-1 0-1.66-1.34-3-3-3-1.05 0-1.96.54-2.5 1.35l-.5.67-.5-.68C10.96 2.54 10.05 2 9 2 7.34 2 6 3.34 6 5c0 .35.07.69.18 1H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-5-2c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zM9 4c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm11 15H4v-2h16v2zm0-5H4V8h5.08L7 10.83 8.62 12 11 8.76l1-1.36 1 1.36L15.38 12 17 10.83 14.92 8H20v6z"/>
                            </svg>
                        </div>
                        <div class="product-details">
                            <h4>{{ $item->nama_barang }}</h4>
                            <p>{{ $item->kode_barang }}</p>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="stok-badge {{ $item->stok > 50 ? 'high' : ($item->stok > 20 ? 'medium' : 'low') }}">
                        {{ number_format($item->stok, 0, ',', '.') }} Unit
                    </span>
                </td>
                <td>{{ $item->updated_at->format('d M Y') }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="3">
                    <div class="empty-state">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path d="M20 6h-2.18c.11-.31.18-.65.18-1 0-1.66-1.34-3-3-3-1.05 0-1.96.54-2.5 1.35l-.5.67-.5-.68C10.96 2.54 10.05 2 9 2 7.34 2 6 3.34 6 5c0 .35.07.69.18 1H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-5-2c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zM9 4c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm11 15H4v-2h16v2zm0-5H4V8h5.08L7 10.83 8.62 12 11 8.76l1-1.36 1 1.36L15.38 12 17 10.83 14.92 8H20v6z"/>
                        </svg>
                        <h3>Belum Ada Data Stok</h3>
                        <p>Mulai tambahkan stok untuk produk Anda</p>
                    </div>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="modal" id="stokModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Tambah Stok Produk</h3>
            <button class="modal-close" onclick="closeModal()">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                </svg>
            </button>
        </div>
        <form id="stokForm" action="{{ url('admin/stok/store') }}" method="POST">
            @csrf
            <div class="modal-body">
                <div class="form-group">
                    <label>Cari Produk <span class="required">*</span></label>
                    <div class="search-product-box">
                        <input type="text" class="search-product-input" id="productSearch" placeholder="Cari nama atau kode produk..." autocomplete="off">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                        </svg>
                        <div class="product-dropdown" id="productDropdown"></div>
                    </div>
                </div>

                <div id="selectedProductsSection" style="display: none;">
                    <div class="selected-products-list">
                        <div class="selected-products-header">
                            Produk Dipilih (<span id="selectedCount">0</span>)
                        </div>
                        <div id="selectedProductsContainer"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal()">Batal</button>
                <button type="submit" class="btn-submit">Simpan Stok</button>
            </div>
        </form>
    </div>
</div>
<script>
let selectedProducts = [];

document.getElementById('btnAddStok').addEventListener('click', function() {
    document.getElementById('stokForm').reset();
    selectedProducts = [];
    updateSelectedProductsDisplay();
    document.getElementById('stokModal').classList.add('show');

    setTimeout(() => {
        document.getElementById('productSearch').focus();
    }, 200);
});


document.getElementById('productSearch').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        const keyword = this.value.trim();
        const dropdown = document.getElementById('productDropdown');

        if(keyword.length === 0) return;

        fetch('{{ url("admin/stok/search-products") }}?keyword=' + keyword)
            .then(res => res.json())
            .then(data => {
                if(data.length > 0) {
                    selectProduct(data[0]);
                    this.value = '';
                    dropdown.classList.remove('show');
                } else {
                    dropdown.innerHTML = `
                        <div style="padding: 15px; text-align:center; font-size:13px; color:#ef4444;">
                            Produk tidak ditemukan
                        </div>
                    `;
                    dropdown.classList.add('show');
                }
            })
            .catch(err => console.error(err));
    }
});


document.getElementById('productSearch').addEventListener('input', function() {
    const keyword = this.value.toLowerCase();
    const dropdown = document.getElementById('productDropdown');

    if(keyword.length < 2) {
        dropdown.classList.remove('show');
        return;
    }

    fetch('{{ url("admin/stok/search-products") }}?keyword=' + keyword)
        .then(res => res.json())
        .then(data => {
            if(data.length === 0) {
                dropdown.innerHTML = '<div style="padding:15px; text-align:center; color:#94a3b8;">Produk tidak ditemukan</div>';
            } else {
                dropdown.innerHTML = data.map(product => `
                    <div class="product-item" onclick='selectProduct(${JSON.stringify(product)})'>
                        <div class="product-item-name">${product.nama_barang}</div>
                        <div class="product-item-code">${product.kode_barang}</div>
                        <div class="product-item-stok">Stok: ${product.stok} unit</div>
                    </div>
                `).join('');
            }
            dropdown.classList.add('show');
        });
});


function selectProduct(product) {
    const exists = selectedProducts.find(p => p.id === product.id);

    if(!exists) {
        selectedProducts.push({
            id: product.id,
            nama_barang: product.nama_barang,
            kode_barang: product.kode_barang,
            stok: product.stok,
            tambah_stok: 0
        });
    }

    updateSelectedProductsDisplay();

    document.getElementById('productSearch').value = '';
    document.getElementById('productDropdown').classList.remove('show');

    setTimeout(() => {
        document.getElementById('productSearch').focus();
    }, 100);
}


function removeProduct(id) {
    selectedProducts = selectedProducts.filter(p => p.id !== id);
    updateSelectedProductsDisplay();
}

function updateStokValue(id, value) {
    const product = selectedProducts.find(p => p.id === id);
    if(product) product.tambah_stok = parseInt(value) || 0;
}

function updateSelectedProductsDisplay() {
    const section = document.getElementById('selectedProductsSection');
    const container = document.getElementById('selectedProductsContainer');
    const count = document.getElementById('selectedCount');

    if(selectedProducts.length === 0) {
        section.style.display = 'none';
        return;
    }

    section.style.display = 'block';
    count.textContent = selectedProducts.length;

    container.innerHTML = selectedProducts.map(product => `
        <div class="selected-product-row">
            <div class="selected-product-info">
                <div class="selected-product-name">${product.nama_barang}</div>
                <div class="selected-product-current">Stok saat ini: <span>${product.stok} unit</span></div>
            </div>
            <div class="stok-input-group">
                <input type="number" class="stok-input" 
                       name="stok[${product.id}]"
                       min="1"
                       placeholder="0"
                       oninput="updateStokValue(${product.id}, this.value)"
                       required>
                <button class="btn-remove" onclick="removeProduct(${product.id})">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                    </svg>
                </button>
            </div>
        </div>
    `).join('');
}


document.getElementById('stokForm').addEventListener('submit', function(e) {
    if(selectedProducts.length === 0) {
        e.preventDefault();
        alert("Pilih minimal 1 produk");
        return false;
    }

    if(selectedProducts.some(p => p.tambah_stok <= 0)) {
        e.preventDefault();
        alert("Nilai stok harus lebih dari 0");
        return false;
    }
});


document.addEventListener('click', function(e) {
    if(!e.target.closest('.search-product-box')) {
        document.getElementById('productDropdown').classList.remove('show');
    }
});

function closeModal() {
    document.getElementById('stokModal').classList.remove('show');
}
</script>

@endsection
