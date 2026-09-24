
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Kasir POS V2</title>
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
        .search-item-row:hover {
            background-color: #dbeafe;
            cursor: pointer;
        }
        .search-item-selected {
            background-color: #bfdbfe;
            outline: 2px solid #3b82f6;
        }
        .cart-table-container {
        max-height: 45vh;
        overflow-y: auto;
        border: 1px solid #ddd;
        border-radius: 6px;
    }

    .waiting-table-container {
        max-height: 20vh;
        overflow-y: auto;
        border: 1px solid #ddd;
        border-radius: 6px;
    }

    .waiting-table-container table thead {
        position: sticky;
        top: 0;
        background: #ffedd5;
        z-index: 2;
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

@if (session('waiting_confirmation'))
<script>
document.addEventListener('DOMContentLoaded', function() {
    const waitingData = @json(session('waiting_confirmation'));
    const barangInfo = @json(session('barang_info'));
    
    Swal.fire({
    title: `
        <div style="font-size:20px; font-weight:700; color:#1e293b;">
            Harga Stok Baru Berbeda
        </div>
    `,
    html: `
        <div style="text-align:left; font-size:14px; color:#334155;">

            <div style="margin-bottom:12px;">
                <div style="font-size:13px; color:#64748b;">Nama Barang:</div>
                <div style="font-size:17px; font-weight:600; color:#0f172a;">
                    ${barangInfo.nama}
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:12px;">

                <div style="
                    padding:12px; 
                    border-radius:10px; 
                    border:1px solid #e2e8f0; 
                    background:#f8fafc;
                ">
                    <div style="font-weight:600; margin-bottom:4px; color:#475569;">Harga Lama</div>
                    <div style="font-size:12px; color:#64748b;">Beli</div>
                    <div style="font-size:15px; font-weight:700; color:#1e293b;">
                        Rp ${waitingData.harga_beli_lama.toLocaleString('id-ID')}
                    </div>
                    <div style="font-size:12px; margin-top:6px; color:#64748b;">Jual</div>
                    <div style="font-size:15px; font-weight:700; color:#1e293b;">
                        Rp ${waitingData.harga_jual_lama.toLocaleString('id-ID')}
                    </div>
                </div>

                <div style="
                    padding:12px; 
                    border-radius:10px; 
                    border:1px solid #bfdbfe; 
                    background:#eff6ff;
                ">
                    <div style="font-weight:600; margin-bottom:4px; color:#2563eb;">Harga Baru</div>
                    <div style="font-size:12px; color:#3b82f6;">Beli</div>
                    <div style="font-size:15px; font-weight:700; color:#1e40af;">
                        Rp ${waitingData.harga_beli_baru.toLocaleString('id-ID')}
                    </div>
                    <div style="font-size:12px; margin-top:6px; color:#3b82f6;">Jual</div>
                    <div style="font-size:15px; font-weight:700; color:#1e40af;">
                        Rp ${waitingData.harga_jual_baru.toLocaleString('id-ID')}
                    </div>
                </div>

            </div>

            <div style="
                padding:10px; 
                border-radius:10px; 
                background:#fef9c3; 
                border:1px solid #fde047;
                margin-bottom:12px;
                font-size:14px;
            ">
                Stok baru tersedia:
                <strong>${waitingData.stok} pcs</strong>
            </div>

            <div style="text-align:center; font-weight:600; color:#1e293b; font-size:14px;">
                Gunakan harga baru untuk stok berikutnya?
            </div>
        </div>
    `,
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Ya, pakai harga baru",
    cancelButtonText: "Batal",
    buttonsStyling: false,
    customClass: {
        popup: "swal2-rounded swal2-shadow",
        confirmButton: "swal2-confirm-btn",
        cancelButton: "swal2-cancel-btn"
    },
    didRender: () => {
        document.querySelector(".swal2-confirm-btn").style.cssText =
            "padding:8px 20px; background:#2563eb; color:white; border-radius:8px; font-weight:600; border:none; margin:5px;";

        document.querySelector(".swal2-cancel-btn").style.cssText =
            "padding:8px 20px; background:#e2e8f0; color:#1e293b; border-radius:8px; font-weight:600; border:none; margin:5px;";
    },
    allowOutsideClick: false
}).then((result) => {
        if (result.isConfirmed) {
            fetch("{{ url('/kasir2/confirm-waiting') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    barang_id: barangInfo.id
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: 'Harga diupdate dan stok ditambahkan',
                        confirmButtonColor: '#10b981',
                        timer: 2000
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal!',
                        text: data.message || 'Terjadi kesalahan',
                        confirmButtonColor: '#ef4444'
                    });
                }
            });
        }
    });
});
</script>
@endif
@if (session('error'))
<script>
document.addEventListener('DOMContentLoaded', function() {
    @if(session('updated_stocks'))
        const updatedStocks = @json(session('updated_stocks'));
    @endif
    Swal.fire({
        icon: 'error',
        title: 'Error!',
        html: '{!! addslashes(session('error')) !!}',
        confirmButtonText: 'OK',
        confirmButtonColor: '#ef4444',
        allowOutsideClick: false,
        allowEscapeKey: true
    }).then(() => {
        @if(session('updated_stocks'))
            const updatedStocks = @json(session('updated_stocks'));
            updatedStocks.forEach(update => {
                const item = cart.find(i => i.id === update.barang_id);
                if (item) {
                    if (update.available_qty === 0) {
                        cart = cart.filter(i => i.id !== update.barang_id);
                        if (waitingUsage[update.barang_id]) delete waitingUsage[update.barang_id];
                        if (waitingConfirmed[update.barang_id]) delete waitingConfirmed[update.barang_id];
                    } else {
                        item.qty = update.available_qty;
                        item.stok = update.stok;
                        distributeWaitingUsage(item, item.qty);
                    }
                }
            });
            
            if (cart.length > 0) {
                if (selectedItemIndex >= cart.length) selectedItemIndex = cart.length - 1;
                renderCart();
            } else {
                selectedItemIndex = -1;
                renderCart();
            }
        @endif
    });
});
</script>
@endif
<body class="bg-gray-100 h-screen overflow-hidden">

<div class="h-screen flex flex-col p-4">

    <div class="bg-white p-3 rounded-lg shadow mb-3">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-xl font-bold text-gray-800">KASIR V2</h1>
                <p class="text-xs text-gray-600">Point of Sale System with PIN</p>
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
            <p class="text-xs text-gray-600">Scan barcode atau tekan F12 untuk cari barang</p>
        </div>
    </div>

    <div class="flex-1 bg-white shadow rounded-lg p-4 flex flex-col min-h-0">
        <h2 class="text-lg font-bold text-gray-800 mb-3">Keranjang Belanja</h2>

        <div id="cartEmpty" class="flex-1 flex items-center justify-center text-gray-400 text-lg">
            Belum ada barang dalam keranjang
        </div>

        <div id="cartTable" class="hidden flex-1 flex flex-col min-h-0">
            <div id="cartScrollContainer" class="cart-table-container overflow-y-auto border border-gray-200 rounded mb-2">
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
            <div id="usedWaitingSection" class="hidden mt-3">
    <h3 class="text-sm font-bold text-gray-800">Detail Barang Waiting</h3>

    <div class="waiting-table-container mt-2">
        <table class="w-full text-sm">
            <thead>
                <tr>
                    <th class="px-3 py-2 text-left">Nama</th>
                    <th class="px-3 py-2 text-center">Qty</th>
                    <th class="px-3 py-2 text-right">Harga</th>
                </tr>
            </thead>
            <tbody id="usedWaitingBody"></tbody>
        </table>
    </div>
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


                <div class="bg-gray-100 px-6 py-3 rounded border-2 border-gray-300 mt-5">
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

<div id="searchModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-800">Cari Barang<span class="keyboard-hint ml-2">ESC untuk tutup</span></h3>
            <button id="btnCloseSearch" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <div class="mb-4">
            <input type="text" id="searchInput" class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none text-lg" placeholder="Ketik kode barang atau nama barang...">
        </div>

        <div id="searchResults" class="max-h-96 overflow-y-auto border border-gray-200 rounded">
            <div class="p-8 text-center text-gray-400">
                Ketik untuk mencari barang
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

        <div id="rfidStep" class="mb-4 text-center">
            <div class="flex flex-col items-center justify-center p-6 bg-gradient-to-br from-blue-100 to-blue-50 rounded-lg border-2 border-blue-300">
                <svg class="w-16 h-16 text-blue-600 mb-3 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                </svg>
                <p class="text-lg font-bold text-gray-800 mb-1">Tap Kartu RFID</p>
                <p class="text-sm text-gray-600 mb-4">Tempelkan kartu pada reader</p>
                <div class="w-full max-w-sm text-left">
                    <label for="rfidInput" class="block text-xs font-semibold text-gray-500 mb-1">Atau ketik nomor kartu (mode coba)</label>
                    <div class="flex gap-2">
                        <input type="text" id="rfidInput" autocomplete="off" autofocus
                            class="flex-1 px-3 py-2 border-2 border-blue-300 rounded text-center font-mono text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
                            placeholder="UID / nomor kartu">
                        <button type="button" id="rfidSubmitBtn"
                            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded shrink-0">
                            Cek
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div id="saldoStep" class="hidden mb-4">
            <div id="saldoCard" class="p-4 rounded-lg border-2 bg-green-50 border-green-300">
                <p class="text-sm text-gray-600 mb-1">Pemegang Kartu</p>
                <p id="saldoNama" class="text-lg font-bold text-gray-800 mb-2">—</p>
                <p class="text-sm text-gray-600 mb-1">Saldo</p>
                <p id="saldoNilai" class="text-2xl font-bold text-gray-800">—</p>
                <p id="saldoStatus" class="text-sm font-semibold mt-2"></p>
            </div>
        </div>

        <div id="pinStep" class="hidden">
            <div class="mb-4">
                <label class="text-sm font-semibold text-gray-700">Masukkan PIN</label>
                <input type="text" id="pinInput" name="pin_kasir_input" inputmode="numeric" maxlength="6" autocomplete="off" class="w-full mt-1 px-4 py-3 border-2 border-blue-300 rounded text-center text-2xl font-bold tracking-widest" placeholder="••••••">
                <p class="text-xs text-gray-500 mt-1 text-center">Tekan ENTER untuk membayar</p>
            </div>
        </div>

        <div id="onlineProcessing" class="hidden">
            <div class="flex flex-col items-center justify-center p-6">
                <div class="spinner mb-3"></div>
                <p id="onlineProcessingText" class="text-sm text-gray-600">Memproses pembayaran...</p>
            </div>
        </div>
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
let waitingBarang = [];
let barcodeBuffer = '';
let barcodeTimeout = null;
let useDiscount = false;
let selectedItemIndex = -1;
let isProcessingPayment = false;
let currentRfid = '';
let searchResults = [];
let selectedSearchIndex = -1;
let waitingUsage = {};
let pendingWaiting = null;
let allowIncrease = false;
let waitingConfirmed = {};
let realPin = "";
let isAddingToCart = false;
let processingQueue = [];

document.getElementById('pinInput').addEventListener('input', function(e) {
    const cursorPos = this.selectionStart;
    let val = this.value.replace(/[^0-9•]/g, "");
    const bulletCount = (this.value.match(/•/g) || []).length;
    const newDigits = val.replace(/•/g, "");
    if (newDigits.length > 0) {
        const beforeCursor = realPin.substring(0, cursorPos - 1);
        const afterCursor = realPin.substring(cursorPos - 1);
        realPin = beforeCursor + newDigits + afterCursor;
    } else if (val.length < bulletCount) {
        realPin = realPin.substring(0, val.length);
    }
    if (realPin.length > 6) {
        realPin = realPin.slice(0, 6);
    }
    this.value = "•".repeat(realPin.length);
    const newCursorPos = Math.min(cursorPos, this.value.length);
    this.setSelectionRange(newCursorPos, newCursorPos);
});

document.getElementById('pinInput').addEventListener('keydown', function(e) {
    const cursorPos = this.selectionStart;
    if (e.key === 'Backspace') {
        e.preventDefault();
        if (realPin.length > 0 && cursorPos > 0) {
            realPin = realPin.slice(0, cursorPos - 1) + realPin.slice(cursorPos);
            this.value = "•".repeat(realPin.length);
            this.setSelectionRange(cursorPos - 1, cursorPos - 1);
        }
    } else if (e.key === 'Delete') {
        e.preventDefault();
        if (realPin.length > 0 && cursorPos < realPin.length) {
            realPin = realPin.slice(0, cursorPos) + realPin.slice(cursorPos + 1);
            this.value = "•".repeat(realPin.length);
            this.setSelectionRange(cursorPos, cursorPos);
        }
    } else if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
        return;
    } else if (e.key === 'Enter') {
        if (realPin && currentRfid) {
            processOnlinePayment(currentRfid, realPin);
        }
    }
});

function getRealPin() {
    return realPin;
}

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

const waitingBarangData = [
    @foreach($waiting_barangs ?? [] as $wb)
    {
        id: {{ $wb->id }},
        barang_id: {{ $wb->barang_id }},
        kode_barang: '{{ $wb->kode_barang }}',
        nama: '{{ $wb->barang->nama_barang ?? 'Unknown' }}',
        stok: {{ $wb->stok }},
        harga_beli: {{ $wb->harga_beli ?? 0 }},
        harga_jual: {{ $wb->harga_jual ?? 0 }}
    },
    @endforeach
];

@if(session('error') && session('cart_data'))
cart = @json(session('cart_data'));
waitingUsage = @json(session('waiting_usage') ?? []);
waitingConfirmed = @json(session('waiting_confirmed') ?? []);
useDiscount = {{ session('use_discount') ? 'true' : 'false' }};
selectedItemIndex = 0;
renderCart();
renderUsedWaiting();
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
async function addToCart(id, kode_barang, name, price, stok, diskon) {
    if (isAddingToCart) {
        processingQueue.push({ id, kode_barang, name, price, stok, diskon });
        return;
    }
    
    isAddingToCart = true;
    
    try {
        const exists = cart.find(item => item.id === id);
        
        if (exists) {
            const waitingList = waitingBarangData.filter(w => w.barang_id === id);
            const waitingTotal = waitingList.reduce((a,b)=>a+b.stok,0);
            const maxQty = stok + waitingTotal;
            
            if (exists.qty >= maxQty) {
                showNotification(`Stok ${name} tidak mencukupi!`, 'warning');
                return;
            }
            
            exists.qty += 1;
            renderCart();
            showNotification(`${name} qty +1`, 'success');
        } else {
            if (stok <= 0) {
                const waitingList = waitingBarangData.filter(w => w.barang_id === id);
                if (waitingList.length === 0 || waitingList.reduce((a,b)=>a+b.stok,0) === 0) {
                    showNotification(`${name} stok habis!`, 'error');
                    return;
                }
                cart.push({ id, kode_barang, name, price, stok, diskon, qty: 1 });
                waitingUsage[id] = { total: 1, perWaiting: { [waitingList[0].id]: 1 } };
                renderUsedWaiting();
                renderCart();
                showNotification(`${name} ditambahkan`, 'success');
                return;
            }
            cart.push({ id, kode_barang, name, price, stok, diskon, qty: 1 });
            renderCart();
            showNotification(`${name} ditambahkan`, 'success');
        }
        
        if (selectedItemIndex === -1 && cart.length > 0) {
            selectedItemIndex = 0;
            highlightSelectedRow();
        }
    } finally {
        isAddingToCart = false;
        
        if (processingQueue.length > 0) {
            const next = processingQueue.shift();
            setTimeout(() => addToCart(next.id, next.kode_barang, next.name, next.price, next.stok, next.diskon), 10);
        }
    }
}

let lastBarcodeTime = 0;
const BARCODE_DEBOUNCE = 300;

function addByBarcode(kodeBarang) {
    const now = Date.now();
    if (now - lastBarcodeTime < BARCODE_DEBOUNCE) {
        return;
    }
    lastBarcodeTime = now;
    
    const barang = barangData.find(b => b.kode_barang.toLowerCase() === kodeBarang.toLowerCase());
    if (barang) {
        addToCart(barang.id, barang.kode_barang, barang.nama, barang.harga, barang.stok, barang.diskon);
    } else {
        showNotification('Kode tidak ditemukan!', 'error');
    }
}

function renderUsedWaiting() {
  const body = document.getElementById('usedWaitingBody');
  const section = document.getElementById('usedWaitingSection');
  body.innerHTML = '';
  let hasData = false;

  for (const id in waitingUsage) {
    const usage = waitingUsage[id];
    if (!usage || usage.total === 0) continue;

    const item = cart.find(i => i.id == id);
    if (!item) continue;

    for (const wid in usage.perWaiting) {
      const qty = usage.perWaiting[wid];
      if (qty <= 0) continue;

      const w = waitingBarangData.find(x => x.id == wid);
      if (!w) {
        continue;
      }

      hasData = true;
      body.innerHTML += `
        <tr class="border-b">
          <td class="px-3 py-2">${item.name}</td>
          <td class="px-3 py-2 text-center">${qty}</td>
          <td class="px-3 py-2 text-right">${formatRupiah(w.harga_jual)}</td>
        </tr>
      `;
    }
  }

  if (hasData) section.classList.remove('hidden');
  else section.classList.add('hidden');
}


function getNextWaitingPrice(barangId, currentQty, baseStok) {
    const waitingList = waitingBarangData.filter(w => w.barang_id === barangId).sort((a, b) => a.id - b.id);
    let accumulated = baseStok;
    for (const w of waitingList) {
        accumulated += w.stok;
        if (currentQty <= accumulated) {
            return w;
        }
    }
    return null;
}

function showWaitingPricePopup(item, w, newQty, baseStok) {
    const oldPrice = item.price;
    Swal.fire({
        title: `<div style="font-size:22px; font-weight:700; color:#1e293b;">Perbedaan Harga Stok Baru</div>`,
        html: `
            <div style="text-align:left; font-size:15px; color:#334155; line-height:1.5;">
                <div style="margin-bottom:15px;">
                    <div style="color:#64748b; font-size:14px;">Barang:</div>
                    <div style="font-weight:700; font-size:17px; color:#0f172a;">${item.name}</div>
                </div>
                <div style="display:flex; gap:12px; margin-bottom:15px;">
                    <div style="flex:1;padding:12px;border-radius:10px;background:#f8fafc;border:1px solid #e2e8f0;">
                        <div style="font-weight:600; margin-bottom:4px; color:#475569;">Harga Sebelumnya</div>
                        <div style="font-size:14px; color:#64748b;">Harga yang digunakan sebelumnya</div>
                        <div style="font-size:20px; font-weight:700; margin-top:4px; color:#1e293b;">${formatRupiah(oldPrice)}</div>
                    </div>
                    <div style="flex:1;padding:12px;border-radius:10px;background:#eff6ff;border:1px solid #bfdbfe;">
                        <div style="font-weight:600; margin-bottom:4px; color:#2563eb;">Harga Baru</div>
                        <div style="font-size:14px; color:#3b82f6;">Untuk stok yang baru masuk</div>
                        <div style="font-size:20px; font-weight:700; margin-top:4px; color:#1e40af;">${formatRupiah(w.harga_jual)}</div>
                        <div style="font-size:13px; color:#2563eb; margin-top:4px;">Stok tersedia: ${w.stok} pcs</div>
                    </div>
                </div>
                <div style="text-align:center; font-size:15px; font-weight:600; color:#1e293b;">Gunakan harga baru untuk stok yang baru masuk?</div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: "Lanjut",
        cancelButtonText: "Cancel",
        buttonsStyling: false,
        allowOutsideClick: false,
        allowEscapeKey: false,
        customClass: { confirmButton: "swal-confirm", cancelButton: "swal-cancel" },
        didRender: () => {
            document.querySelector(".swal-confirm").style.cssText = "padding:10px 18px; background:#2563eb; color:white; border:none; border-radius:8px; font-weight:600; margin-right:10px;";
            document.querySelector(".swal-cancel").style.cssText = "padding:10px 18px; background:#ef4444; color:white; border:none; border-radius:8px; font-weight:600;";
        }
    }).then(result => {
        if (result.isConfirmed) {
            if (!waitingConfirmed[item.id]) waitingConfirmed[item.id] = {};
            waitingConfirmed[item.id][w.id] = true;
            item.qty = newQty;
            distributeWaitingUsage(item, newQty);
            renderUsedWaiting();
            renderCart();
        }
    });
}
function distributeWaitingUsage(item, totalQty) {
    const baseStok = item.stok;
    const waitingList = waitingBarangData.filter(w => w.barang_id === item.id).sort((a, b) => a.id - b.id);
    let remaining = Math.max(totalQty - baseStok, 0);
    if (!waitingUsage[item.id]) {
        waitingUsage[item.id] = { total: 0, perWaiting: {} };
    }
    waitingUsage[item.id].perWaiting = {};
    waitingUsage[item.id].total = remaining;
    for (const w of waitingList) {
        if (remaining <= 0) break;
        const allocate = Math.min(remaining, w.stok);
        waitingUsage[item.id].perWaiting[w.id] = allocate;
        remaining -= allocate;
    }
}

function renderCart() {
    const tbody = document.getElementById('cartTableBody');
    const empty = document.getElementById('cartEmpty');
    const table = document.getElementById('cartTable');

    if (cart.length === 0) {
        tbody.innerHTML = '';
        table.classList.add('hidden');
        empty.classList.remove('hidden');
        selectedItemIndex = -1;
        renderUsedWaiting();
        return;
    }

    table.classList.remove('hidden');
    empty.classList.add('hidden');
    tbody.innerHTML = '';

    let total = 0;
    let totalDiskon = 0;

    cart.forEach((item, index) => {
        const fresh = barangData.find(x => x.id === item.id);
        if (fresh) {
            item.stok = fresh.stok;
            item.price = fresh.harga;
        }

        const wList = waitingBarangData.filter(w => w.barang_id === item.id);
        const wTotal = wList.reduce((a,b)=>a+b.stok, 0);

        if (!waitingUsage[item.id]) waitingUsage[item.id] = { total: 0, perWaiting: {} };

        const neededWaiting = Math.max(item.qty - item.stok, 0);
        waitingUsage[item.id].total = neededWaiting;
        waitingUsage[item.id].perWaiting = {};

        let remain = neededWaiting;
        for (const w of wList) {
            if (remain <= 0) break;
            const alloc = Math.min(remain, w.stok);
            waitingUsage[item.id].perWaiting[w.id] = alloc;
            remain -= alloc;
        }

        const baseQty = Math.min(item.qty, item.stok);
        const subtotalBase = baseQty * item.price;

        let subtotalWaiting = 0;
        for (const wid in waitingUsage[item.id].perWaiting) {
            const q = waitingUsage[item.id].perWaiting[wid];
            const w = wList.find(x => x.id == wid);
            if (w) subtotalWaiting += q * w.harga_jual;
        }

        const subtotal = subtotalBase + subtotalWaiting;

        let diskonNominal = 0;
        if (useDiscount && item.diskon > 0) {
            diskonNominal = Math.floor(subtotal * (item.diskon / 100));
        }

        total += subtotal;
        totalDiskon += diskonNominal;

        let displayPrice = item.price;
        if (item.qty > item.stok) {
            const firstWid = Object.keys(waitingUsage[item.id].perWaiting)[0];
            const fw = wList.find(x => x.id == firstWid);
            if (fw) displayPrice = fw.harga_jual;
        }

        tbody.innerHTML += `
            <tr class="cart-row border-b" data-row-index="${index}">
                <td class="px-3 py-2 text-sm">${index + 1}</td>
                <td class="px-3 py-2 text-sm">${item.kode_barang}</td>
                <td class="px-3 py-2 text-sm">${item.name}</td>
                <td class="px-3 py-2 text-center text-sm font-bold">
                    <div class="flex justify-center items-center gap-2">
                        <button class="qty-btn bg-red-500 text-white px-2 py-1 rounded text-xs font-bold" data-item-id="${item.id}" data-action="minus">−</button>
                        <span class="min-w-[20px] inline-block">${item.qty}</span>
                        <button class="qty-btn bg-blue-500 text-white px-2 py-1 rounded text-xs font-bold" data-item-id="${item.id}" data-action="plus">+</button>
                    </div>
                </td>
                <td class="px-3 py-2 text-right text-sm">${formatRupiah(displayPrice)}</td>
                <td class="px-3 py-2 text-right text-sm ${useDiscount ? '' : 'hidden'}">${formatRupiah(diskonNominal)}</td>
                <td class="px-3 py-2 text-right text-sm font-bold">${formatRupiah(subtotal - diskonNominal)}</td>
            </tr>
        `;
    });

    document.getElementById('totalAmount').textContent = formatRupiah(total - totalDiskon);
    if (useDiscount) {
        document.getElementById('diskonHeader').classList.remove('hidden');
        document.getElementById('diskonInfo').classList.remove('hidden');
        document.getElementById('totalDiskonAmount').textContent = formatRupiah(totalDiskon);
    } else {
        document.getElementById('diskonHeader').classList.add('hidden');
        document.getElementById('diskonInfo').classList.add('hidden');
    }

    highlightSelectedRow();
    renderUsedWaiting();
}


document.addEventListener("DOMContentLoaded", () => {
    renderCart();
});


let isUpdatingQty = false;

function updateQty(id, change) {
    if (isUpdatingQty) return;
    isUpdatingQty = true;
    
    const item = cart.find(i => i.id === id);
    if (!item) {
        isUpdatingQty = false;
        return;
    }
    
    const newQty = item.qty + change;
    
    if (newQty < 1) {
        cart = cart.filter(i => i.id !== id);
        if (waitingUsage[id]) {
            delete waitingUsage[id];
        }
        if (selectedItemIndex >= cart.length) {
            selectedItemIndex = cart.length - 1;
        }
        isUpdatingQty = false;
        renderCart();
        renderUsedWaiting();
        showNotification(`${item.name} dihapus dari keranjang`, 'success');
        return;
    }
    
    const baseStok = item.stok;
    const waitingList = waitingBarangData.filter(w => w.barang_id === id).sort((a, b) => a.id - b.id);
    const waitingTotal = waitingList.reduce((a,b)=>a+b.stok,0);
    const maxQty = baseStok + waitingTotal;
    
    if (change === 1 && newQty > maxQty) {
    isUpdatingQty = false;
    showNotification('Stok tidak mencukupi!', 'error');
    return;
}
    
    if (change === 1 && newQty > baseStok) {
        const nextWaiting = getNextWaitingPrice(id, newQty, baseStok);
        if (nextWaiting) {
            let currentPrice = item.price;
            
            if (item.qty > baseStok && waitingUsage[id] && waitingUsage[id].perWaiting) {
                const lastUsedWaitingId = Object.keys(waitingUsage[id].perWaiting)
                    .filter(wid => waitingUsage[id].perWaiting[wid] > 0)
                    .sort((a, b) => parseInt(b) - parseInt(a))[0];
                if (lastUsedWaitingId) {
                    const lastW = waitingBarangData.find(w => w.id == lastUsedWaitingId);
                    if (lastW) currentPrice = lastW.harga_jual;
                }
            }
            
            if (nextWaiting.harga_jual !== currentPrice) {
                if (!waitingConfirmed[id] || !waitingConfirmed[id][nextWaiting.id]) {
                    isUpdatingQty = false;
                    showWaitingPricePopup(item, nextWaiting, newQty, baseStok);
                    return;
                }
            }
        }
    }
    
    item.qty = newQty;
    distributeWaitingUsage(item, newQty);
    isUpdatingQty = false;
    renderUsedWaiting();
    renderCart();
}
document.getElementById('cartTableBody').addEventListener('click', (e) => {
  const btn = e.target.closest('.qty-btn');
  if (!btn) return;

  const itemId = parseInt(btn.dataset.itemId, 10);
  const action = btn.dataset.action;

  updateQty(itemId, action === 'plus' ? 1 : -1);
});

document.getElementById('cartTableBody').addEventListener('click', (e) => {
  const btn = e.target.closest('.qty-btn');
  if (btn) return;

  const row = e.target.closest('.cart-row');
  if (!row) return;

  selectedItemIndex = parseInt(row.dataset.rowIndex, 10);
  highlightSelectedRow();
});

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

function showSearchModal() {
    document.getElementById('searchModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    searchResults = [];
    selectedSearchIndex = -1;
    document.getElementById('searchInput').value = '';
    document.getElementById('searchResults').innerHTML = '<div class="p-8 text-center text-gray-400">Ketik untuk mencari barang</div>';
    setTimeout(() => document.getElementById('searchInput').focus(), 100);
}

function closeSearchModal() {
    document.getElementById('searchModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function searchBarang(query) {
    if (!query || query.length === 0) {
        searchResults = [];
        selectedSearchIndex = -1;
        document.getElementById('searchResults').innerHTML = '<div class="p-8 text-center text-gray-400">Ketik untuk mencari barang</div>';
        return;
    }
    const lowerQuery = query.toLowerCase();
    searchResults = barangData.filter(b => b.kode_barang.toLowerCase().includes(lowerQuery) || b.nama.toLowerCase().includes(lowerQuery));
    selectedSearchIndex = searchResults.length > 0 ? 0 : -1;
    renderSearchResults();
}

function renderSearchResults() {
    const container = document.getElementById('searchResults');
    if (searchResults.length === 0) {
        container.innerHTML = '<div class="p-8 text-center text-gray-400">Tidak ada barang ditemukan</div>';
        return;
    }
    let html = '<table class="w-full">';
    html += '<thead class="bg-gray-100 sticky top-0"><tr>';
    html += '<th class="px-4 py-2 text-left text-sm font-bold">KODE</th>';
    html += '<th class="px-4 py-2 text-left text-sm font-bold">NAMA</th>';
    html += '<th class="px-4 py-2 text-right text-sm font-bold">HARGA</th>';
    html += '<th class="px-4 py-2 text-center text-sm font-bold">STOK</th>';
    html += '</tr></thead><tbody>';
    searchResults.forEach((item, index) => {
        const selectedClass = index === selectedSearchIndex ? 'search-item-selected' : '';
        html += `<tr data-search-index="${index}" class="search-item-row ${selectedClass} border-b">`;
        html += `<td class="px-4 py-3 text-sm">${item.kode_barang}</td>`;
        html += `<td class="px-4 py-3 text-sm font-medium">${item.nama}</td>`;
        html += `<td class="px-4 py-3 text-sm text-right">${formatRupiah(item.harga)}</td>`;
        html += `<td class="px-4 py-3 text-sm text-center">${item.stok}</td>`;
        html += '</tr>';
    });
    html += '</tbody></table>';
    container.innerHTML = html;
    attachSearchRowEvents();
    if (selectedSearchIndex >= 0) {
        const selectedRow = container.querySelector(`[data-search-index="${selectedSearchIndex}"]`);
        if (selectedRow) {
            selectedRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
}

function attachSearchRowEvents() {
    document.querySelectorAll('.search-item-row').forEach(row => {
        row.addEventListener('click', function() {
            const index = parseInt(this.dataset.searchIndex);
            addSearchItemToCart(index);
        });
    });
}

function moveSearchSelection(direction) {
    if (searchResults.length === 0) return;
    if (direction === 'down') {
        selectedSearchIndex = (selectedSearchIndex + 1) % searchResults.length;
    } else if (direction === 'up') {
        selectedSearchIndex = (selectedSearchIndex - 1 + searchResults.length) % searchResults.length;
    }
    renderSearchResults();
}

function addSearchItemToCart(index) {
    if (index < 0 || index >= searchResults.length) return;
    const item = searchResults[index];
    addToCart(item.id, item.kode_barang, item.nama, item.harga, item.stok, item.diskon);
    document.getElementById('searchInput').value = '';
    document.getElementById('searchInput').focus();
    searchBarang('');
}

let searchTimeout;
document.getElementById('searchInput').addEventListener('input', function(e) {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        searchBarang(e.target.value);
    }, 150);
});

document.getElementById('btnCloseSearch').addEventListener('click', closeSearchModal);

document.addEventListener('keydown', function(e) {
    const cashModal = document.getElementById('cashModal');
    const onlineModal = document.getElementById('onlineModal');
    const searchModal = document.getElementById('searchModal');
    const isCashModalOpen = !cashModal.classList.contains('hidden');
    const isOnlineModalOpen = !onlineModal.classList.contains('hidden');
    const isSearchModalOpen = !searchModal.classList.contains('hidden');
    if (e.key === 'F12') {
        e.preventDefault();
        if (!isCashModalOpen && !isOnlineModalOpen && !isSearchModalOpen) {
            showSearchModal();
        }
        return;
    }
    if (e.key === 'Escape') {
        if (isCashModalOpen) {
            closeCashModal();
        } else if (isOnlineModalOpen && !isProcessingPayment) {
            closeOnlineModal();
        } else if (isSearchModalOpen) {
            closeSearchModal();
        }
        return;
    }
    if (isSearchModalOpen) {
        if (e.key === 'ArrowUp') {
            e.preventDefault();
            moveSearchSelection('up');
            return;
        }
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            moveSearchSelection('down');
            return;
        }
        if (e.key === 'Enter') {
            e.preventDefault();
            if (selectedSearchIndex >= 0) {
                addSearchItemToCart(selectedSearchIndex);
            }
            return;
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
    const searchModal = document.getElementById('searchModal');
    const isCashModalOpen = !cashModal.classList.contains('hidden');
    const isOnlineModalOpen = !onlineModal.classList.contains('hidden');
    const isSearchModalOpen = !searchModal.classList.contains('hidden');
    
    if (e.target.tagName === 'INPUT' && e.target.id === 'cashPaid') return;
    if (e.target.tagName === 'INPUT' && e.target.id === 'searchInput') return;
    if (e.target.tagName === 'INPUT' && e.target.id === 'pinInput') return;
    if (isCashModalOpen && e.target.tagName !== 'INPUT') return;
    if (isOnlineModalOpen && e.target.tagName !== 'INPUT') return;
    if (isSearchModalOpen) return;
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
    
    if (isAddingToCart) return;
    
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
const barangMelebihiStok = cart.filter(item => {
const baseStok = item.stok;
const waitingList = waitingBarangData.filter(w => w.barang_id === item.id);
const waitingTotal = waitingList.reduce((a,b)=>a+b.stok,0);
const maxQty = baseStok + waitingTotal;
return item.qty > maxQty;
});
if (barangMelebihiStok.length > 0) {
    const listBarang = barangMelebihiStok.map(item => {
        const baseStok = item.stok;
        const waitingList = waitingBarangData.filter(w => w.barang_id === item.id);
        const waitingTotal = waitingList.reduce((a, b) => a + b.stok, 0);
        const maxQty = baseStok + waitingTotal;
        const kurang = item.qty - maxQty;

        return `
            <div style="margin-bottom:10px; padding:10px; border:1px solid #fee2e2; background:#fff1f2; border-radius:8px;">
                <div style="font-weight:700; color:#991b1b;">${item.name}</div>
                <div style="font-size:13px; color:#7f1d1d; margin-top:4px;">
                    Diminta: <b>${item.qty}</b> pcs<br>
                    Stok tersedia: <b>${maxQty}</b> pcs <br>
                </div>
            </div>
        `;
    }).join('');

    Swal.fire({
        icon: 'error',
        title: 'Stok Tidak Cukup!',
        html: `
            <div style="text-align:left; font-size:14px;">
                Barang berikut stok nya kurang:
                <div style="margin-top:12px;">${listBarang}</div>
            </div>
        `,
        confirmButtonText: 'OK',
        confirmButtonColor: '#ef4444'
    });
    return;
}

useDiscount = false;
renderCart();
let total = 0;
cart.forEach(item => {
const baseQty = Math.min(item.qty, item.stok);
const subtotalBase = baseQty * item.price;
let subtotalWaiting = 0;
if (waitingUsage[item.id] && waitingUsage[item.id].perWaiting) {
for (const wid in waitingUsage[item.id].perWaiting) {
const qty = waitingUsage[item.id].perWaiting[wid];
const w = waitingBarangData.find(x => x.id == wid);
if (w) {
subtotalWaiting += qty * w.harga_jual;
}
}
}
total += subtotalBase + subtotalWaiting;
});
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
const cartData = cart.map(item => {
const usage = waitingUsage[item.id] || { total: 0, perWaiting: {} };
return {
id: item.id,
qty: item.qty,
waitingUsage: usage.perWaiting
};
});
const form = document.createElement('form');
form.method = 'POST';
form.action = "{{ url('/kasir2/checkout/tunai/process') }}";
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
const baseQty = Math.min(item.qty, item.stok);
const baseSubtotal = baseQty * item.price;
let waitingSubtotal = 0;
if (waitingUsage[item.id] && waitingUsage[item.id].perWaiting) {
for (const wid in waitingUsage[item.id].perWaiting) {
const qty = waitingUsage[item.id].perWaiting[wid];
const w = waitingBarangData.find(x => x.id == wid);
if (w) {
waitingSubtotal += qty * w.harga_jual;
}
}
}
const subtotalSebelum = baseSubtotal + waitingSubtotal;
const diskonNominal = Math.floor((item.diskon / 100) * subtotalSebelum);
totalSebelum += subtotalSebelum;
totalDiskon += diskonNominal;
});
const grandTotal = totalSebelum - totalDiskon;
document.getElementById('onlineTotal').textContent = formatRupiah(grandTotal);
const onlineDiskon = document.getElementById('onlineDiskon');
if (totalDiskon > 0) {
    onlineDiskon.textContent = `Hemat ${formatRupiah(totalDiskon)}`;
    onlineDiskon.classList.remove('hidden');
} else {
    onlineDiskon.textContent = '';
    onlineDiskon.classList.add('hidden');
}
document.getElementById('rfidInput').value = '';
document.getElementById('pinInput').value = '';
realPin = '';
document.getElementById('onlineProcessing').classList.add('hidden');
document.getElementById('rfidStep').classList.remove('hidden');
document.getElementById('pinStep').classList.add('hidden');
document.getElementById('saldoStep').classList.add('hidden');
currentRfid = '';
window._onlineGrandTotal = grandTotal;
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
currentRfid = '';
realPin = '';
document.getElementById('rfidStep').classList.remove('hidden');
document.getElementById('pinStep').classList.add('hidden');
document.getElementById('saldoStep').classList.add('hidden');
document.getElementById('onlineProcessing').classList.add('hidden');
}

function getCsrfToken() {
    const el = document.querySelector('meta[name="csrf-token"]');
    return el ? el.content : '';
}

document.getElementById('rfidInput').addEventListener('keypress', function(e) {
if (e.key === 'Enter' && !isProcessingPayment) {
const rfid = e.target.value.trim();
if (rfid) {
currentRfid = rfid;
inquirySaldoThenPin(rfid);
}
e.target.value = '';
}
});

function submitRfidFromInput() {
    if (isProcessingPayment) return;
    const inp = document.getElementById('rfidInput');
    const rfid = inp ? inp.value.trim() : '';
    if (!rfid) {
        if (inp) inp.focus();
        return;
    }
    currentRfid = rfid;
    inquirySaldoThenPin(rfid);
    if (inp) inp.value = '';
}

const rfidSubmitBtn = document.getElementById('rfidSubmitBtn');
if (rfidSubmitBtn) {
    rfidSubmitBtn.addEventListener('click', submitRfidFromInput);
}

function inquirySaldoThenPin(rfid) {
    if (isProcessingPayment) return;
    isProcessingPayment = true;
    document.getElementById('rfidStep').classList.add('hidden');
    document.getElementById('pinStep').classList.add('hidden');
    document.getElementById('saldoStep').classList.add('hidden');
    document.getElementById('onlineProcessing').classList.remove('hidden');
    document.getElementById('onlineProcessingText').textContent = 'Mengecek saldo...';

    const total = Number(window._onlineGrandTotal) || 0;

    fetch("{{ url('/kasir2/inquiry-saldo') }}", {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ pid: rfid })
    })
    .then(function(res) {
        return res.json().then(function(body) {
            return { okHttp: res.ok, body: body };
        });
    })
    .then(function(r) {
        document.getElementById('onlineProcessing').classList.add('hidden');
        document.getElementById('saldoStep').classList.remove('hidden');

        const body = r.body || {};
        const nama = body.nama || '—';
        const saldo = Number(body.saldo) || 0;
        const saldoCard = document.getElementById('saldoCard');
        const saldoStatus = document.getElementById('saldoStatus');

        document.getElementById('saldoNama').textContent = nama;
        document.getElementById('saldoNilai').textContent = formatRupiah(saldo);

        if (!r.okHttp || !body.ok) {
            saldoCard.className = 'p-4 rounded-lg border-2 bg-red-50 border-red-300';
            saldoStatus.className = 'text-sm font-semibold mt-2 text-red-700';
            saldoStatus.textContent = body.error || 'Kartu tidak terdaftar';
            isProcessingPayment = false;
            currentRfid = '';
            setTimeout(function() {
                document.getElementById('rfidStep').classList.remove('hidden');
                document.getElementById('saldoStep').classList.add('hidden');
                document.getElementById('rfidInput').focus();
            }, 3500);
            return;
        }

        if (saldo < total) {
            saldoCard.className = 'p-4 rounded-lg border-2 bg-red-50 border-red-300';
            saldoStatus.className = 'text-sm font-semibold mt-2 text-red-700';
            saldoStatus.textContent = 'Saldo tidak mencukupi (total ' + formatRupiah(total) + ')';
            isProcessingPayment = false;
            currentRfid = '';
            setTimeout(function() {
                document.getElementById('rfidStep').classList.remove('hidden');
                document.getElementById('saldoStep').classList.add('hidden');
                document.getElementById('rfidInput').focus();
            }, 3500);
            return;
        }

        saldoCard.className = 'p-4 rounded-lg border-2 bg-green-50 border-green-300';
        saldoStatus.className = 'text-sm font-semibold mt-2 text-green-700';
        saldoStatus.textContent = 'Saldo cukup. Masukkan PIN untuk membayar.';
        isProcessingPayment = false;
        document.getElementById('pinStep').classList.remove('hidden');
        realPin = '';
        document.getElementById('pinInput').value = '';
        setTimeout(function() {
            document.getElementById('pinInput').focus();
        }, 100);
    })
    .catch(function(err) {
        document.getElementById('onlineProcessing').classList.add('hidden');
        document.getElementById('saldoStep').classList.remove('hidden');
        document.getElementById('saldoCard').className = 'p-4 rounded-lg border-2 bg-red-50 border-red-300';
        document.getElementById('saldoNama').textContent = '—';
        document.getElementById('saldoNilai').textContent = '—';
        document.getElementById('saldoStatus').className = 'text-sm font-semibold mt-2 text-red-700';
        document.getElementById('saldoStatus').textContent = (err && err.message) || 'Gagal cek saldo';
        isProcessingPayment = false;
        currentRfid = '';
        setTimeout(function() {
            document.getElementById('rfidStep').classList.remove('hidden');
            document.getElementById('saldoStep').classList.add('hidden');
            document.getElementById('rfidInput').focus();
        }, 3500);
    });
}

function processOnlinePayment(rfid, pin) {
if (isProcessingPayment) return;
isProcessingPayment = true;
document.getElementById('onlineProcessing').classList.remove('hidden');
document.getElementById('onlineProcessingText').textContent = 'Memproses pembayaran...';
document.getElementById('pinStep').classList.add('hidden');
const cartData = cart.map(item => {
const usage = waitingUsage[item.id] || { total: 0, perWaiting: {} };
return {
id: item.id,
qty: item.qty,
waitingUsage: usage.perWaiting
};
});
const form = document.createElement('form');
form.method = 'POST';
form.action = "{{ url('/kasir2/checkout/online/process') }}";
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
const pidInput = document.createElement('input');
pidInput.type = 'hidden';
pidInput.name = 'pid';
pidInput.value = rfid;
form.appendChild(pidInput);
const pinHidden = document.createElement('input');
pinHidden.type = 'hidden';
pinHidden.name = 'pin';
pinHidden.value = pin;
form.appendChild(pinHidden);
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