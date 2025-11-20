<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Kasir POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .focused-row {
            outline: 3px solid #3b82f6;
            outline-offset: -3px;
            background-color: #dbeafe !important;
        }
        .keyboard-hint {
            display: inline-block;
            background: #1f2937;
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            margin-left: 8px;
        }
        .keyboard-guide {
            position: fixed;
            bottom: 20px;
            left: 20px;
            background: rgba(31, 41, 55, 0.95);
            color: white;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 12px;
            z-index: 40;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
        }
        .keyboard-guide-item {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 4px 0;
        }
        .keyboard-guide-key {
            background: #374151;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: bold;
            min-width: 60px;
            text-align: center;
        }
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3b82f6;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .swal2-popup {
            font-family: inherit;
        }
    </style>
</head>

@if (session('error'))
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        icon: 'error',
        title: 'Error!',
        html: '{!! addslashes(session('error')) !!}',
        confirmButtonText: 'OK',
        confirmButtonColor: '#ef4444',
        allowOutsideClick: false,
        allowEscapeKey: true
    });
});
</script>
@endif

@if (session('success'))
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: '{{ session('success') }}',
        confirmButtonText: 'OK',
        confirmButtonColor: '#10b981',
        timer: 3000,
        timerProgressBar: true
    });
});
</script>
@endif

<body class="bg-gray-100 h-screen overflow-hidden">

<div class="h-screen flex flex-col p-4">

    <div class="bg-white p-3 rounded-lg shadow mb-3">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-xl font-bold text-gray-800">KASIR</h1>
                <p class="text-xs text-gray-600">Point of Sale System</p>
            </div>
            
            <div class="flex items-center gap-2">
                <div class="bg-gray-50 px-3 py-2 rounded border border-gray-200">
                    <p id="currentTime" class="text-sm font-bold text-gray-800"></p>
                    <p id="currentDate" class="text-xs text-gray-600"></p>
                </div>

                <div class="bg-blue-50 px-3 py-2 rounded border-2 border-blue-200 flex items-center gap-2">
                    <div class="bg-blue-500 p-1.5 rounded-full">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-600 font-semibold">Kasir Toko</p>
                    </div>
                </div>

               <button id="btnLogout" 
    class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded font-bold transition-all flex items-center gap-1">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
    </svg>
    Logout
</button>

<form id="logoutForm" action="{{ route('logout') }}" method="POST" class="hidden">
    @csrf
</form>

            </div>
        </div>
    </div>

    <div class="bg-green-50 p-2 rounded border border-green-200 mb-3 flex items-center gap-2">
        <div class="relative">
            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
            </svg>
            <span id="barcodePulse" class="absolute -top-1 -right-1 flex h-2 w-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
            </span>
        </div>
        <div>
            <p class="text-sm font-bold text-gray-800">Barcode Scanner Aktif</p>
            <p class="text-xs text-gray-600">Scan barcode untuk menambahkan barang</p>
        </div>
    </div>

    <div class="flex-1 bg-white shadow rounded-lg p-4 flex flex-col min-h-0">
        <h2 class="text-lg font-bold text-gray-800 mb-3">Keranjang Belanja</h2>

        <div id="cartEmpty" class="flex-1 flex items-center justify-center text-gray-400 text-lg">
            Belum ada barang dalam keranjang
        </div>

        <div id="cartTable" class="hidden flex-1 flex flex-col min-h-0">
            <div id="cartScrollContainer" class="flex-1 overflow-y-auto border border-gray-200 rounded mb-3">
                <table class="w-full">
                    <thead class="bg-gray-800 text-white sticky top-0" style="z-index:1;">
                        <tr>
                            <th class="px-3 py-2 text-left text-sm font-bold">NO</th>
                            <th class="px-3 py-2 text-left text-sm font-bold">KODE</th>
                            <th class="px-3 py-2 text-left text-sm font-bold">NAMA</th>
                            <th class="px-3 py-2 text-center text-sm font-bold">QTY</th>
                            <th class="px-3 py-2 text-right text-sm font-bold">HARGA</th>
                            <th id="diskonHeader" class="hidden px-3 py-2 text-right text-sm font-bold">DISKON</th>
                            <th class="px-3 py-2 text-right text-sm font-bold">SUBTOTAL</th>
                        </tr>
                    </thead>
                    <tbody id="cartTableBody"></tbody>
                </table>
            </div>

            <div class="flex justify-between items-end">
                <div>
                    <button id="btnCash" class="px-6 py-2 bg-green-600 text-white rounded font-bold hover:bg-green-700 transition-all">
                        Cash<span class="keyboard-hint">F1</span>
                    </button>
                    <button id="btnOnline" class="ml-2 px-6 py-2 bg-blue-600 text-white rounded font-bold hover:bg-blue-700 transition-all">
                        Cashless<span class="keyboard-hint">F2</span>
                    </button>
                </div>

                <div class="bg-gray-100 px-6 py-3 rounded border-2 border-gray-300">
                    <div class="text-right">
                        <p id="diskonInfo" class="hidden text-xs text-red-600 mb-1 font-semibold">DISKON: <span id="totalDiskonAmount">Rp 0</span></p>
                        <p class="text-xs text-gray-600 mb-1 font-semibold">TOTAL</p>
                        <p id="totalAmount" class="text-2xl font-bold text-gray-800">Rp 0</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<div id="cashModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-800">Pembayaran Tunai<span class="keyboard-hint ml-2">ESC untuk batal</span></h3>
            <button id="btnCloseCash" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <div class="mb-3">
            <label class="text-sm font-semibold text-gray-700">Total Belanja</label>
            <input type="text" id="cashTotal" class="w-full mt-1 px-4 py-2 bg-gray-100 border rounded font-bold text-lg" readonly>
        </div>

        <div class="mb-3">
            <label class="text-sm font-semibold text-gray-700">Uang Dibayar</label>
            <input type="text" id="cashPaid" class="w-full mt-1 px-4 py-2 border rounded" placeholder="Masukkan nominal">
        </div>

        <div class="mb-4">
            <label class="text-sm font-semibold text-gray-700">Kembalian</label>
            <input type="text" id="cashChange" class="w-full mt-1 px-4 py-2 bg-green-100 border rounded font-bold text-lg" readonly>
        </div>

        <button id="btnProcessCash" disabled class="w-full py-3 bg-gray-400 text-white rounded font-semibold cursor-not-allowed transition">
            Bayar Sekarang<span class="keyboard-hint">ENTER</span>
        </button>
    </div>
</div>

<div id="onlineModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-800">Pembayaran Cashless<span class="keyboard-hint ml-2">ESC untuk batal</span></h3>
            <button id="btnCloseOnline" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <div class="mb-4 text-center p-4 bg-blue-50 rounded border border-blue-200">
            <p class="text-sm text-gray-600 mb-1">Total Pembayaran</p>
            <p id="onlineTotal" class="text-3xl font-bold text-gray-800">Rp 0</p>
            <p id="onlineDiskon" class="text-xs text-red-600 mt-1 font-semibold"></p>
        </div>

        <div class="mb-4 text-center">
            <div class="flex flex-col items-center justify-center p-6 bg-gradient-to-br from-blue-100 to-blue-50 rounded-lg border-2 border-blue-300">
                <svg class="w-16 h-16 text-blue-600 mb-3 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                </svg>
                <p class="text-lg font-bold text-gray-800 mb-1">Tap Kartu RFID</p>
                <p class="text-sm text-gray-600">Tempelkan kartu pada reader</p>
            </div>
        </div>

        <div id="onlineProcessing" class="hidden">
            <div class="flex flex-col items-center justify-center p-6">
                <div class="spinner mb-3"></div>
                <p class="text-sm text-gray-600">Memproses pembayaran...</p>
            </div>
        </div>

        <input type="text" id="rfidInput" class="opacity-0 absolute -z-10" autofocus>
    </div>
</div>

<div id="notification" class="hidden fixed top-6 right-6 px-6 py-3 rounded-lg shadow-lg transform transition-all duration-300 z-50">
    <div class="flex items-center gap-3">
        <svg id="notifIcon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"></svg>
        <p id="notifText" class="font-semibold text-sm"></p>
    </div>
</div>

<script>
let cart = [];
let barcodeBuffer = '';
let barcodeTimeout = null;
let useDiscount = false;
let selectedItemIndex = -1;
let isProcessingPayment = false;

const barangData = [
    @foreach($barangs as $b)
    {
        id: {{ $b->id }},
        kode_barang: '{{ $b->kode_barang }}',
        nama: '{{ $b->nama_barang }}',
        harga: {{ $b->harga_jual }},
        stok: {{ $b->stok }},
        diskon: {{ isset($b->diskon) ? $b->diskon->nilai ?? 0 : 0 }}
    },
    @endforeach
];

@if(session('error') && session('cart_data'))
cart = @json(session('cart_data'));
selectedItemIndex = 0;
renderCart();
@endif

function updateDateTime() {
    const now = new Date();
    const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');
    
    document.getElementById('currentTime').textContent = `${hours}:${minutes}:${seconds}`;
    document.getElementById('currentDate').textContent = `${days[now.getDay()]}, ${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()}`;
}

updateDateTime();
setInterval(updateDateTime, 1000);

function showNotification(message, type = 'success') {
    const notif = document.getElementById('notification');
    const notifText = document.getElementById('notifText');
    const notifIcon = document.getElementById('notifIcon');
    
    notifText.textContent = message;
    
    if (type === 'success') {
        notif.className = 'fixed top-6 right-6 px-6 py-3 rounded-lg shadow-lg transform transition-all duration-300 z-50 bg-green-500 text-white';
        notifIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>';
    } else if (type === 'warning') {
        notif.className = 'fixed top-6 right-6 px-6 py-3 rounded-lg shadow-lg transform transition-all duration-300 z-50 bg-orange-500 text-white';
        notifIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>';
    } else {
        notif.className = 'fixed top-6 right-6 px-6 py-3 rounded-lg shadow-lg transform transition-all duration-300 z-50 bg-red-500 text-white';
        notifIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>';
    }
    
    notif.classList.remove('hidden');
    setTimeout(() => notif.classList.add('hidden'), 3000);
}

function addToCart(id, kode_barang, name, price, stok, diskon) {
    const exists = cart.find(item => item.id === id);
    
    if (exists) {
        if (exists.qty >= stok) {
            showNotification(`Stok ${name} tidak mencukupi!`, 'warning');
            return;
        }
        exists.qty += 1;
    } else {
        if (stok <= 0) {
            showNotification(`${name} stok habis!`, 'error');
            return;
        }
        cart.push({ id, kode_barang, name, price, stok, diskon, qty: 1 });
    }
    
    renderCart();
    showNotification(`${name} ditambahkan`, 'success');
    
    if (selectedItemIndex === -1 && cart.length > 0) {
        selectedItemIndex = 0;
        highlightSelectedRow();
    }
}

function addByBarcode(kodeBarang) {
    const barang = barangData.find(b => b.kode_barang.toLowerCase() === kodeBarang.toLowerCase());
    
    if (barang) {
        addToCart(barang.id, barang.kode_barang, barang.nama, barang.harga, barang.stok, barang.diskon);
    } else {
        showNotification('Kode tidak ditemukan!', 'error');
    }
}

function renderCart() {
    const tbody = document.getElementById("cartTableBody");
    const empty = document.getElementById("cartEmpty");
    const table = document.getElementById("cartTable");
    const diskonHeader = document.getElementById("diskonHeader");
    const diskonInfo = document.getElementById("diskonInfo");
    
    tbody.innerHTML = "";
    
    if (cart.length === 0) {
        empty.classList.remove("hidden");
        table.classList.add("hidden");
        selectedItemIndex = -1;
        return;
    }
    
    empty.classList.add("hidden");
    table.classList.remove("hidden");
    
    if (useDiscount) {
        diskonHeader.classList.remove("hidden");
        diskonInfo.classList.remove("hidden");
    } else {
        diskonHeader.classList.add("hidden");
        diskonInfo.classList.add("hidden");
    }
    
    let totalSebelumDiskon = 0;
    let totalDiskon = 0;
    
    cart.forEach((item, index) => {
        const subtotalSebelum = item.price * item.qty;
        const diskonNominal = useDiscount ? Math.floor((item.diskon / 100) * subtotalSebelum) : 0;
        const subtotal = subtotalSebelum - diskonNominal;
        
        totalSebelumDiskon += subtotalSebelum;
        totalDiskon += diskonNominal;
        
        const diskonCell = useDiscount ? `<td class="px-3 py-2 text-right text-sm text-red-600">${item.diskon}% (${formatRupiah(diskonNominal)})</td>` : '';
        
        const rowClass = index % 2 === 0 ? 'bg-gray-50' : 'bg-white';
        
        tbody.innerHTML += `
            <tr data-row-index="${index}" class="cart-row ${rowClass}">
                <td class="px-3 py-2 text-sm font-semibold">${index + 1}</td>
                <td class="px-3 py-2 text-sm">${item.kode_barang}</td>
                <td class="px-3 py-2 text-sm font-medium">${item.name}</td>
                <td class="px-3 py-2">
                    <div class="flex items-center justify-center gap-1">
                        <button data-item-id="${item.id}" data-action="minus" class="qty-btn px-2 py-1 bg-gray-200 hover:bg-gray-300 rounded text-sm font-bold">−</button>
                        <span class="font-bold text-sm min-w-[2rem] text-center">${item.qty}</span>
                        <button data-item-id="${item.id}" data-action="plus" class="qty-btn px-2 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm font-bold ${item.qty >= item.stok ? 'opacity-50' : ''}">+</button>
                    </div>
                </td>
                <td class="px-3 py-2 text-right text-sm">${formatRupiah(item.price)}</td>
                ${diskonCell}
                <td class="px-3 py-2 text-right text-sm font-bold">${formatRupiah(subtotal)}</td>
            </tr>
        `;
    });
    
    const grandTotal = totalSebelumDiskon - totalDiskon;
    
    document.getElementById('totalAmount').textContent = formatRupiah(grandTotal);
    document.getElementById('totalDiskonAmount').textContent = formatRupiah(totalDiskon);
    
    attachQtyButtonEvents();
    highlightSelectedRow();
}

function attachQtyButtonEvents() {
    document.querySelectorAll('.qty-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const itemId = parseInt(this.dataset.itemId);
            const action = this.dataset.action;
            updateQty(itemId, action === 'plus' ? 1 : -1);
        });
    });
}

function updateQty(id, change) {
    const item = cart.find(i => i.id === id);
    if (!item) return;
    
    const newQty = item.qty + change;
    
    if (newQty > item.stok) {
        showNotification(`Stok tidak mencukupi!`, 'warning');
        return;
    }
    
    item.qty = newQty;
    
    if (item.qty <= 0) {
        const itemIndex = cart.findIndex(i => i.id === id);
        cart = cart.filter(i => i.id !== id);
        
        if (selectedItemIndex >= cart.length) {
            selectedItemIndex = cart.length - 1;
        }
    }
    
    renderCart();
}

function highlightSelectedRow() {
    document.querySelectorAll('.cart-row').forEach(row => {
        row.classList.remove('focused-row');
    });
    
    if (selectedItemIndex >= 0 && selectedItemIndex < cart.length) {
        const selectedRow = document.querySelector(`[data-row-index="${selectedItemIndex}"]`);
        if (selectedRow) {
            selectedRow.classList.add('focused-row');
            selectedRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
}

function moveSelection(direction) {
    if (cart.length === 0) return;
    
    if (selectedItemIndex === -1) {
        selectedItemIndex = 0;
    } else {
        if (direction === 'down') {
            selectedItemIndex = (selectedItemIndex + 1) % cart.length;
        } else if (direction === 'up') {
            selectedItemIndex = (selectedItemIndex - 1 + cart.length) % cart.length;
        }
    }
    
    highlightSelectedRow();
}

function updateSelectedItemQty(change) {
    if (selectedItemIndex < 0 || selectedItemIndex >= cart.length) return;
    
    const item = cart[selectedItemIndex];
    updateQty(item.id, change);
}

function deleteSelectedItem() {
    if (selectedItemIndex < 0 || selectedItemIndex >= cart.length) return;
    
    const item = cart[selectedItemIndex];
    cart = cart.filter(i => i.id !== item.id);
    
    if (selectedItemIndex >= cart.length) {
        selectedItemIndex = cart.length - 1;
    }
    
    renderCart();
    showNotification(`${item.name} dihapus`, 'success');
}

document.addEventListener('keydown', function(e) {
    const cashModal = document.getElementById('cashModal');
    const onlineModal = document.getElementById('onlineModal');
    const isCashModalOpen = !cashModal.classList.contains('hidden');
    const isOnlineModalOpen = !onlineModal.classList.contains('hidden');
    
    if (e.key === 'Escape') {
        if (isCashModalOpen) {
            closeCashModal();
        } else if (isOnlineModalOpen && !isProcessingPayment) {
            closeOnlineModal();
        }
        return;
    }
    
    if (isCashModalOpen) {
        if (e.key === 'Enter') {
            const btn = document.getElementById('btnProcessCash');
            if (!btn.disabled) {
                processCashPayment();
            }
        }
        return;
    }
    
    if (isOnlineModalOpen) {
        return;
    }
    
    if (e.key === 'F1') {
        e.preventDefault();
        showCashPayment();
        return;
    }
    
    if (e.key === 'F2') {
        e.preventDefault();
        showOnlinePayment();
        return;
    }
    
    if (e.key === 'ArrowUp') {
        e.preventDefault();
        moveSelection('up');
        return;
    }
    
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        moveSelection('down');
        return;
    }
    
    if (e.key === '+' || e.key === '=') {
        e.preventDefault();
        updateSelectedItemQty(1);
        return;
    }
    
    if (e.key === '-' || e.key === '_') {
        e.preventDefault();
        updateSelectedItemQty(-1);
        return;
    }
    
    if (e.key === 'Delete') {
        e.preventDefault();
        deleteSelectedItem();
        return;
    }
});

document.addEventListener('keypress', function(e) {
    const cashModal = document.getElementById('cashModal');
    const onlineModal = document.getElementById('onlineModal');
    const isCashModalOpen = !cashModal.classList.contains('hidden');
    const isOnlineModalOpen = !onlineModal.classList.contains('hidden');
    
    if (e.target.tagName === 'INPUT' && e.target.id === 'cashPaid') return;
    if (isCashModalOpen && e.target.tagName !== 'INPUT') return;
    if (isOnlineModalOpen && e.target.tagName !== 'INPUT') return;
    
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
    
    clearTimeout(barcodeTimeout);
    
    if (e.key === 'Enter') {
        if (barcodeBuffer.trim().length > 0) {
            addByBarcode(barcodeBuffer.trim());
            barcodeBuffer = '';
        }
    } else {
        barcodeBuffer += e.key;
        barcodeTimeout = setTimeout(() => {
            if (barcodeBuffer.trim().length > 0) {
                addByBarcode(barcodeBuffer.trim());
            }
            barcodeBuffer = '';
        }, 100);
    }
});

document.getElementById('btnCash').addEventListener('click', showCashPayment);
document.getElementById('btnOnline').addEventListener('click', showOnlinePayment);
document.getElementById('btnCloseCash').addEventListener('click', closeCashModal);
document.getElementById('btnCloseOnline').addEventListener('click', function() {
    if (!isProcessingPayment) {
        closeOnlineModal();
    }
});
document.getElementById('btnLogout').addEventListener('click', function () {
    if (confirm('Yakin ingin logout?')) {
        document.getElementById('logoutForm').submit();
    }
});

function showCashPayment() {
    if (cart.length === 0) {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Keranjang kosong!',
            confirmButtonText: 'OK',
            confirmButtonColor: '#ef4444'
        });
        return;
    }
    
    const hasStockError = cart.some(item => item.qty > item.stok);
    if (hasStockError) {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Ada barang yang melebihi stok!',
            confirmButtonText: 'OK',
            confirmButtonColor: '#ef4444'
        });
        return;
    }
    
    useDiscount = false;
    renderCart();
    
    const total = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
    document.getElementById('cashTotal').value = formatRupiah(total);
    document.getElementById('cashTotal').dataset.total = total;
    document.getElementById('cashPaid').value = '';
    document.getElementById('cashChange').value = '';
    document.getElementById('btnProcessCash').disabled = true;
    document.getElementById('btnProcessCash').className = 'w-full py-3 bg-gray-400 text-white rounded font-semibold cursor-not-allowed transition';
    
    document.getElementById('cashModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    setTimeout(() => document.getElementById('cashPaid').focus(), 100);
}

function closeCashModal() {
    document.getElementById('cashModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

document.getElementById('cashPaid').addEventListener('input', function() {
    let raw = this.value.replace(/[^0-9]/g, "");
    this.value = formatRupiah(raw);
    
    let total = parseInt(document.getElementById('cashTotal').dataset.total);
    let paid = parseInt(raw);
    let btn = document.getElementById('btnProcessCash');
    
    if (paid >= total) {
        document.getElementById('cashChange').value = formatRupiah(paid - total);
        btn.disabled = false;
        btn.className = 'w-full py-3 bg-green-600 hover:bg-green-700 text-white rounded font-semibold transition';
    } else {
        document.getElementById('cashChange').value = 'Belum cukup';
        btn.disabled = true;
        btn.className = 'w-full py-3 bg-gray-400 text-white rounded font-semibold cursor-not-allowed transition';
    }
});

document.getElementById('btnProcessCash').addEventListener('click', processCashPayment);

function processCashPayment() {
    const paid = document.getElementById('cashPaid').value;
    
   const cartData = cart.map(item => ({
    id: item.id,
    kode_barang: item.kode_barang,
    name: item.name,
    qty: item.qty,
    harga: item.price,
    stok: item.stok,
    diskon: item.diskon,
    subtotal: item.price * item.qty
}));

    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/kasir/checkout/tunai/process';
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]');
    if (csrfToken) {
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = csrfToken.content;
        form.appendChild(csrfInput);
    }
    
    const cartInput = document.createElement('input');
    cartInput.type = 'hidden';
    cartInput.name = 'cart';
    cartInput.value = JSON.stringify(cartData);
    form.appendChild(cartInput);
    
    const bayarInput = document.createElement('input');
    bayarInput.type = 'hidden';
    bayarInput.name = 'bayar';
    bayarInput.value = paid;
    form.appendChild(bayarInput);
    
    document.body.appendChild(form);
    form.submit();
}

function showOnlinePayment() {
    if (cart.length === 0) {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Keranjang kosong!',
            confirmButtonText: 'OK',
            confirmButtonColor: '#ef4444'
        });
        return;
    }
    
    useDiscount = true;
    renderCart();
    
    let totalSebelum = 0;
    let totalDiskon = 0;
    
    cart.forEach(item => {
        const subtotalSebelum = item.price * item.qty;
        const diskonNominal = Math.floor((item.diskon / 100) * subtotalSebelum);
        totalSebelum += subtotalSebelum;
        totalDiskon += diskonNominal;
    });
    
    const grandTotal = totalSebelum - totalDiskon;
    
    document.getElementById('onlineTotal').textContent = formatRupiah(grandTotal);
    document.getElementById('onlineDiskon').textContent = `Hemat ${formatRupiah(totalDiskon)} dari diskon`;
    document.getElementById('rfidInput').value = '';
    document.getElementById('onlineProcessing').classList.add('hidden');
    
    document.getElementById('onlineModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    isProcessingPayment = false;
    
    setTimeout(() => {
        document.getElementById('rfidInput').focus();
    }, 100);
}

function closeOnlineModal() {
    document.getElementById('onlineModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
    isProcessingPayment = false;
}

document.getElementById('rfidInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter' && !isProcessingPayment) {
        const rfid = e.target.value.trim();
        if (rfid) {
            processOnlinePayment(rfid);
        }
        e.target.value = '';
    }
});

function processOnlinePayment(rfid) {
    if (isProcessingPayment) return;
    
    isProcessingPayment = true;
    document.getElementById('onlineProcessing').classList.remove('hidden');
    
    let totalSebelumDiskon = 0;
    let totalDiskon = 0;
    
    const cartData = cart.map(item => {
        const subtotalSebelum = item.price * item.qty;
        const diskonNominal = Math.floor((item.diskon / 100) * subtotalSebelum);
        const subtotal = subtotalSebelum - diskonNominal;
        
        totalSebelumDiskon += subtotalSebelum;
        totalDiskon += diskonNominal;
        
        return {
            id: item.id,
            kode_barang: item.kode_barang,
            name: item.name,
            qty: item.qty,
            harga: item.price,
            stok: item.stok,
            diskon: item.diskon,
            diskon_nominal: diskonNominal,
            subtotal: subtotal,
            price: item.price
        };
    });
    
    const grandTotal = totalSebelumDiskon - totalDiskon;
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/kasir/checkout/online/process';
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]');
    if (csrfToken) {
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = csrfToken.content;
        form.appendChild(csrfInput);
    }
    
    const itemsInput = document.createElement('input');
    itemsInput.type = 'hidden';
    itemsInput.name = 'items';
    itemsInput.value = JSON.stringify(cartData);
    form.appendChild(itemsInput);
    
    const totalInput = document.createElement('input');
    totalInput.type = 'hidden';
    totalInput.name = 'total';
    totalInput.value = totalSebelumDiskon;
    form.appendChild(totalInput);
    
    const diskonInput = document.createElement('input');
    diskonInput.type = 'hidden';
    diskonInput.name = 'diskon_nominal';
    diskonInput.value = totalDiskon;
    form.appendChild(diskonInput);
    
    const grandTotalInput = document.createElement('input');
    grandTotalInput.type = 'hidden';
    grandTotalInput.name = 'grand_total';
    grandTotalInput.value = grandTotal;
    form.appendChild(grandTotalInput);
    
    const noCustInput = document.createElement('input');
    noCustInput.type = 'hidden';
    noCustInput.name = 'pid';
    noCustInput.value = rfid;
    form.appendChild(noCustInput);
    
    const cartDataInput = document.createElement('input');
    cartDataInput.type = 'hidden';
    cartDataInput.name = 'cart_data';
    cartDataInput.value = JSON.stringify(cartData);
    form.appendChild(cartDataInput);
    
    document.body.appendChild(form);
    form.submit();
}

function formatRupiah(num) {
    if (isNaN(num) || num === '') return 'Rp 0';
    return 'Rp ' + parseInt(num).toLocaleString('id-ID');
}
</script>

</body>
</html>