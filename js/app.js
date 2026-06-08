// js/app.js
import { createApp, ref, reactive, computed, onMounted, onBeforeUnmount } from 'https://unpkg.com/vue@3/dist/vue.esm-browser.js';

const App = {
    setup() {
        // App State
        const activeTab = ref('order'); // 'order', 'active-orders', 'history', 'admin'
        const theme = ref('light');
        const searchQuery = ref('');
        const selectedCategory = ref('all');
        const products = ref([]);
        const cart = ref([]);
        const activeOrders = ref([]);
        const pastOrders = ref([]);
        const historyFilter = ref('all'); // 'all', 'completed', 'cancelled'

        // Admin Product Form State
        const productForm = reactive({
            id: null,
            name: '',
            price: '',
            category: 'burger',
            status: 1,
            image_url: ''
        });
        const isEditing = ref(false);
        const uploading = ref(false);
        const imagePreview = ref('');

        // Checkout & Receipt Modal State
        const showCheckoutModal = ref(false);
        const showReceiptModal = ref(false);
        const paymentMethod = ref('cash'); // 'cash' only for now
        const cashReceived = ref('');
        const checkoutOrder = ref(null);

        // Toast Notifications
        const toasts = ref([]);
        let toastId = 0;

        // Clock State
        const currentTime = ref('');

        // Polling Timer for Orders
        let pollingInterval = null;

        // --- Helper: Add Toast ---
        const addToast = (message, type = 'success') => {
            const id = ++toastId;
            toasts.value.push({ id, message, type });
            setTimeout(() => {
                toasts.value = toasts.value.filter(t => t.id !== id);
            }, 3500);
        };

        // --- Clock Function ---
        const updateClock = () => {
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const date = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            currentTime.value = `${year}-${month}-${date} ${hours}:${minutes}:${seconds}`;
        };

        // --- Theme Management ---
        const toggleTheme = () => {
            theme.value = theme.value === 'light' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', theme.value);
            localStorage.setItem('pos-theme', theme.value);
            addToast(`已切換為 ${theme.value === 'light' ? '日間' : '夜間'} 模式`, 'info');
        };

        const initTheme = () => {
            const savedTheme = localStorage.getItem('pos-theme') || 'light';
            theme.value = savedTheme;
            document.documentElement.setAttribute('data-theme', savedTheme);
        };

        // --- API Calls ---

        // Fetch all products
        const fetchProducts = async () => {
            try {
                const response = await fetch('api/products.php?all=1');
                if (!response.ok) throw new Error('無法載入商品資料');
                const data = await response.json();
                products.value = data.map(p => ({
                    ...p,
                    id: parseInt(p.id),
                    price: parseFloat(p.price),
                    status: parseInt(p.status)
                }));
            } catch (err) {
                addToast(err.message, 'error');
            }
        };

        // Fetch active orders (pending)
        const fetchActiveOrders = async () => {
            try {
                const response = await fetch('api/orders.php?type=pending');
                if (!response.ok) throw new Error('無法載入現場訂單');
                const data = await response.json();
                activeOrders.value = data.map(order => ({
                    ...order,
                    id: parseInt(order.id),
                    total_price: Math.round(parseFloat(order.total_price)),
                    items: (order.items || []).map(item => ({
                        ...item,
                        id: parseInt(item.id),
                        product_id: parseInt(item.product_id),
                        price: Math.round(parseFloat(item.price)),
                        quantity: parseInt(item.quantity),
                        subtotal: Math.round(parseFloat(item.subtotal))
                    }))
                }));
            } catch (err) {
                console.error(err);
            }
        };

        // Fetch completed/cancelled orders
        const fetchPastOrders = async () => {
            try {
                const response = await fetch('api/orders.php?type=history');
                if (!response.ok) throw new Error('無法載入歷史訂單');
                const data = await response.json();
                pastOrders.value = data.map(order => ({
                    ...order,
                    id: parseInt(order.id),
                    total_price: Math.round(parseFloat(order.total_price)),
                    items: (order.items || []).map(item => ({
                        ...item,
                        id: parseInt(item.id),
                        product_id: parseInt(item.product_id),
                        price: Math.round(parseFloat(item.price)),
                        quantity: parseInt(item.quantity),
                        subtotal: Math.round(parseFloat(item.subtotal))
                    }))
                }));
            } catch (err) {
                console.error(err);
            }
        };

        // --- Cart Operations ---

        const cartTotal = computed(() => {
            return cart.value.reduce((total, item) => total + Math.round(parseFloat(item.subtotal)), 0);
        });

        const cartItemCount = computed(() => {
            return cart.value.reduce((count, item) => count + parseInt(item.quantity), 0);
        });

        const addToCart = (product) => {
            const productId = parseInt(product.id);
            const price = Math.round(parseFloat(product.price));
            const existing = cart.value.find(item => item.product_id === productId);
            if (existing) {
                existing.quantity++;
                existing.subtotal = existing.quantity * price;
            } else {
                cart.value.push({
                    product_id: productId,
                    product_name: product.name,
                    price: price,
                    quantity: 1,
                    subtotal: price,
                    image_url: product.image_url
                });
            }
            addToast(`已加入購物車: ${product.name}`, 'success');
        };

        const removeFromCart = (product) => {
            const productId = parseInt(product.id);
            const index = cart.value.findIndex(item => item.product_id === productId);
            if (index > -1) {
                const item = cart.value[index];
                if (item.quantity > 1) {
                    item.quantity--;
                    item.subtotal = item.quantity * Math.round(parseFloat(item.price));
                } else {
                    cart.value.splice(index, 1);
                }
                addToast(`已從購物車移除/減少: ${product.name}`, 'info');
            }
        };

        const updateQuantity = (item, qty) => {
            const newQty = parseInt(qty);
            if (isNaN(newQty) || newQty <= 0) {
                cart.value = cart.value.filter(i => i.product_id !== item.product_id);
                addToast(`已移除商品: ${item.product_name}`, 'info');
            } else {
                item.quantity = newQty;
                item.subtotal = item.quantity * Math.round(parseFloat(item.price));
            }
        };

        const clearCart = () => {
            cart.value = [];
            addToast('購物車已清空', 'info');
        };

        // --- Filtered Products for POS Order Interface ---
        const filteredProducts = computed(() => {
            // Only show active products in the Ordering Tab
            let activeProducts = products.value.filter(p => p.status === 1);

            if (selectedCategory.value !== 'all') {
                activeProducts = activeProducts.filter(p => p.category === selectedCategory.value);
            }

            if (searchQuery.value.trim() !== '') {
                const query = searchQuery.value.toLowerCase().trim();
                activeProducts = activeProducts.filter(p => p.name.toLowerCase().includes(query));
            }

            return activeProducts;
        });

        // --- Filtered Past Orders for History Tab ---
        const filteredPastOrders = computed(() => {
            if (historyFilter.value === 'all') {
                return pastOrders.value;
            }
            return pastOrders.value.filter(o => o.status === historyFilter.value);
        });

        // --- Checkout Flow ---

        const openCheckout = () => {
            if (cart.value.length === 0) return;
            cashReceived.value = ''; // Reset cash input
            showCheckoutModal.value = true;
        };

        const closeCheckout = () => {
            showCheckoutModal.value = false;
        };

        const selectPresetCash = (amount) => {
            if (amount === 'exact') {
                cashReceived.value = Math.round(cartTotal.value);
            } else {
                // If input is already a number, add it, otherwise set it
                const current = Math.round(parseFloat(cashReceived.value)) || 0;
                // If current is 0, let's just set it to the preset amount for quick operation
                if (current === 0) {
                    cashReceived.value = amount;
                } else {
                    cashReceived.value = current + amount;
                }
            }
        };

        const changeToReturn = computed(() => {
            const received = Math.round(parseFloat(cashReceived.value));
            if (isNaN(received)) return 0;
            return received - Math.round(cartTotal.value);
        });

        const isCheckoutValid = computed(() => {
            if (paymentMethod.value !== 'cash') return false;
            const received = Math.round(parseFloat(cashReceived.value));
            return !isNaN(received) && received >= Math.round(cartTotal.value);
        });

        const submitCheckout = async () => {
            if (!isCheckoutValid.value) return;

            try {
                const orderData = {
                    action: 'create',
                    total_price: Math.round(cartTotal.value),
                    payment_method: paymentMethod.value,
                    items: cart.value.map(item => ({
                        product_id: item.product_id,
                        product_name: item.product_name,
                        price: Math.round(parseFloat(item.price)),
                        quantity: item.quantity,
                        subtotal: Math.round(parseFloat(item.subtotal))
                    }))
                };

                const response = await fetch('api/orders.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(orderData)
                });

                const result = await response.json();
                if (!response.ok) throw new Error(result.error || '結帳交易失敗');

                if (result.success) {
                    checkoutOrder.value = result.order;
                    checkoutOrder.value.total_price = Math.round(parseFloat(result.order.total_price));
                    if (checkoutOrder.value.items) {
                        checkoutOrder.value.items = checkoutOrder.value.items.map(item => ({
                            ...item,
                            price: Math.round(parseFloat(item.price)),
                            subtotal: Math.round(parseFloat(item.subtotal))
                        }));
                    }
                    checkoutOrder.value.cash_received = Math.round(parseFloat(cashReceived.value));
                    checkoutOrder.value.change_returned = Math.round(changeToReturn.value);

                    // Clear cart and show simulated receipt
                    cart.value = [];
                    showCheckoutModal.value = false;
                    showReceiptModal.value = true;
                    addToast('訂單送出成功！', 'success');

                    // Refresh data
                    fetchProducts();
                    fetchActiveOrders();
                }
            } catch (err) {
                addToast(err.message, 'error');
            }
        };

        const closeReceipt = () => {
            showReceiptModal.value = false;
            checkoutOrder.value = null;
        };

        // --- Order Actions (Complete & Cancel) ---

        const updateOrderStatus = async (orderId, targetStatus) => {
            const confirmMsg = targetStatus === 'completed'
                ? '確定將此訂單標記為已完成？'
                : '確定要取消此筆訂單？此操作無法撤銷。';

            if (!confirm(confirmMsg)) return;

            try {
                const response = await fetch('api/orders.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'update_status',
                        id: orderId,
                        status: targetStatus
                    })
                });

                const result = await response.json();
                if (!response.ok) throw new Error(result.error || '操作失敗');

                addToast(result.message, targetStatus === 'completed' ? 'success' : 'warning');
                fetchActiveOrders();
                fetchPastOrders();
            } catch (err) {
                addToast(err.message, 'error');
            }
        };

        // --- Admin: Product Form Functions ---

        const triggerImageUpload = async (event) => {
            const file = event.target.files[0];
            if (!file) return;

            uploading.value = true;
            const formData = new FormData();
            formData.append('file', file);

            try {
                const response = await fetch('api/upload.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.error || '上傳失敗');

                productForm.image_url = result.image_url;
                imagePreview.value = result.image_url;
                addToast('圖片上傳成功！', 'success');
            } catch (err) {
                addToast(err.message, 'error');
            } finally {
                uploading.value = false;
            }
        };

        const removeUploadedImage = () => {
            productForm.image_url = '';
            imagePreview.value = '';
            addToast('已移除選取圖片', 'info');
        };

        const saveProduct = async () => {
            if (!productForm.name.trim()) {
                addToast('請輸入商品名稱', 'error');
                return;
            }
            if (productForm.price === '' || parseFloat(productForm.price) < 0) {
                addToast('價格輸入不正確', 'error');
                return;
            }

            try {
                const response = await fetch('api/products.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: productForm.id,
                        name: productForm.name,
                        price: Math.round(parseFloat(productForm.price)),
                        category: productForm.category,
                        status: productForm.status,
                        image_url: productForm.image_url
                    })
                });

                const result = await response.json();
                if (!response.ok) throw new Error(result.error || '商品儲存失敗');

                addToast(result.message, 'success');
                resetProductForm();
                fetchProducts();
            } catch (err) {
                addToast(err.message, 'error');
            }
        };

        const editProduct = (product) => {
            isEditing.value = true;
            productForm.id = product.id;
            productForm.name = product.name;
            productForm.price = product.price;
            productForm.category = product.category;
            productForm.status = product.status;
            productForm.image_url = product.image_url;
            imagePreview.value = product.image_url;

            // Scroll form into view for responsive screens
            const formPanel = document.querySelector('.admin-form-panel');
            if (formPanel) formPanel.scrollIntoView({ behavior: 'smooth' });
        };

        const toggleProductStatus = async (product) => {
            try {
                const response = await fetch('api/products.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'toggle_status',
                        id: product.id
                    })
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.error || '狀態變更失敗');

                addToast(`商品 ${product.name} 已${result.product.status === 1 ? '上架' : '下架'}`, 'info');
                fetchProducts();
            } catch (err) {
                addToast(err.message, 'error');
            }
        };

        const deleteProduct = async (product) => {
            if (!confirm(`確定要完全刪除商品「${product.name}」嗎？`)) return;

            try {
                const response = await fetch('api/products.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'delete',
                        id: product.id
                    })
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.error || '刪除失敗');

                addToast(result.message, 'success');

                // If currently editing this product, reset form
                if (productForm.id === product.id) {
                    resetProductForm();
                }

                fetchProducts();
            } catch (err) {
                addToast(err.message, 'error');
            }
        };

        const resetProductForm = () => {
            isEditing.value = false;
            productForm.id = null;
            productForm.name = '';
            productForm.price = '';
            productForm.category = 'burger';
            productForm.status = 1;
            productForm.image_url = '';
            imagePreview.value = '';
        };

        const getCategoryName = (key) => {
            const map = {
                'burger': '漢堡',
                'sandwich': '三明治',
                'snack': '點心',
                'drink': '飲品'
            };
            return map[key] || key;
        };

        // --- Lifecycle Hooks ---
        onMounted(() => {
            initTheme();
            updateClock();
            setInterval(updateClock, 1000);

            // Initial Data Load
            fetchProducts();
            fetchActiveOrders();
            fetchPastOrders();

            // Set up polling for active orders list (Real-time updates every 8 seconds)
            pollingInterval = setInterval(() => {
                fetchActiveOrders();
                // Also update products in case other browser added items
                fetchProducts();
            }, 8000);
        });

        onBeforeUnmount(() => {
            if (pollingInterval) clearInterval(pollingInterval);
        });

        return {
            // App state
            activeTab,
            theme,
            searchQuery,
            selectedCategory,
            products,
            cart,
            activeOrders,
            pastOrders,
            historyFilter,

            // Admin Product Form
            productForm,
            isEditing,
            uploading,
            imagePreview,

            // Checkout & Receipt Modal
            showCheckoutModal,
            showReceiptModal,
            paymentMethod,
            cashReceived,
            checkoutOrder,

            // Toast
            toasts,
            currentTime,

            // Computed properties
            cartTotal,
            cartItemCount,
            filteredProducts,
            filteredPastOrders,
            changeToReturn,
            isCheckoutValid,

            // Actions
            toggleTheme,
            addToCart,
            removeFromCart,
            updateQuantity,
            clearCart,
            openCheckout,
            closeCheckout,
            selectPresetCash,
            submitCheckout,
            closeReceipt,
            updateOrderStatus,
            triggerImageUpload,
            removeUploadedImage,
            saveProduct,
            editProduct,
            toggleProductStatus,
            deleteProduct,
            resetProductForm,
            getCategoryName,

            // Manual Refreshes
            fetchActiveOrders,
            fetchPastOrders
        };
    }
};

createApp(App).mount('#app');
