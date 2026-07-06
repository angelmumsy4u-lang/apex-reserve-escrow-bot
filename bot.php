<?php
// ============================================
// CONFIGURATION - EDIT THESE VALUES
// ============================================
$token = '8901870538:AAEY9Af2Q28yhCd0rUw6SW210KplaV9pss0'; 

$admin_chat_id = '8687759153'; 

// YOUR WALLET ADDRESSES FOR EACH CRYPTO
$wallets = [
    'BTC' => 'bc1qx97qd68vuy9s2k6ace9as3svufhdtzuemed54z',
    'SOL' => '412WEHgb39fjm4dG8EDq1NpJ4GY7RMrrojxQvTrfNKHcHs',
    'USDT' => '0x248bAD64b7493ecCbC411d1E3380aEBFF9943701',
    'DOGE' => 'D9eeh5CffFUkpKRckp3EJhJqQrKrFK7pKwgU'
];

$platform_fee = 1.0; // 1% fee

$users_file = 'users.json';
$escrows_file = 'escrows.json';

// ============================================
// TELEGRAM API FUNCTIONS
// ============================================
function sendMessage($chat_id, $message, $keyboard = null) {
    global $token;
    $url = "https://api.telegram.org/bot$token/sendMessage";
    $post_fields = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'Markdown'
    ];
    if ($keyboard) {
        $post_fields['reply_markup'] = json_encode($keyboard);
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    return curl_exec($ch);
}

function answerCallback($callback_id, $text = null) {
    global $token;
    $url = "https://api.telegram.org/bot$token/answerCallbackQuery";
    $post_fields = ['callback_query_id' => $callback_id];
    if ($text) {
        $post_fields['text'] = $text;
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    return curl_exec($ch);
}

function trySendMessage($chat_id, $message, $keyboard = null) {
    global $token;
    $url = "https://api.telegram.org/bot$token/sendMessage";
    $post_fields = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'Markdown'
    ];
    if ($keyboard) {
        $post_fields['reply_markup'] = json_encode($keyboard);
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $response = curl_exec($ch);
    $data = json_decode($response, true);
    
    if (isset($data['ok']) && $data['ok'] == true) {
        return true;
    }
    return false;
}

// ============================================
// DATA MANAGEMENT FUNCTIONS
// ============================================
function loadUsers() {
    global $users_file;
    if (file_exists($users_file)) {
        return json_decode(file_get_contents($users_file), true) ?: [];
    }
    return [];
}

function saveUsers($users) {
    global $users_file;
    file_put_contents($users_file, json_encode($users));
}

function registerUser($chat_id, $username = '', $first_name = '') {
    $users = loadUsers();
    if (!isset($users[$chat_id])) {
        $users[$chat_id] = [
            'username' => $username,
            'first_name' => $first_name,
            'role' => 'user',
            'registered' => time()
        ];
        saveUsers($users);
        return true;
    }
    return false;
}

function loadEscrows() {
    global $escrows_file;
    if (file_exists($escrows_file)) {
        return json_decode(file_get_contents($escrows_file), true) ?: [];
    }
    return [];
}

function saveEscrows($escrows) {
    global $escrows_file;
    file_put_contents($escrows_file, json_encode($escrows));
}

function createEscrow($buyer_id, $seller_username, $seller_wallet, $crypto, $description, $buyer_username) {
    $escrows = loadEscrows();
    $escrow_id = count($escrows) + 1;
    
    $escrows[] = [
        'id' => $escrow_id,
        'buyer_id' => $buyer_id,
        'buyer_username' => $buyer_username,
        'seller_username' => $seller_username,
        'seller_wallet' => $seller_wallet,
        'crypto' => $crypto,
        'description' => $description,
        'status' => 'pending_payment',
        'created_at' => time(),
        'paid_at' => null,
        'completed_at' => null,
        'tx_hash' => null
    ];
    saveEscrows($escrows);
    return $escrow_id;
}

function getEscrow($escrow_id) {
    $escrows = loadEscrows();
    foreach ($escrows as $escrow) {
        if ($escrow['id'] == $escrow_id) {
            return $escrow;
        }
    }
    return null;
}

function updateEscrowStatus($escrow_id, $status) {
    $escrows = loadEscrows();
    foreach ($escrows as &$escrow) {
        if ($escrow['id'] == $escrow_id) {
            $escrow['status'] = $status;
            if ($status == 'paid') {
                $escrow['paid_at'] = time();
            }
            if ($status == 'completed') {
                $escrow['completed_at'] = time();
            }
            break;
        }
    }
    saveEscrows($escrows);
}

function getUserEscrows($chat_id) {
    $escrows = loadEscrows();
    return array_filter($escrows, function($escrow) use ($chat_id) {
        return $escrow['buyer_id'] == $chat_id;
    });
}

// ============================================
// WELCOME & MAIN MENU
// ============================================
function showMainMenu($chat_id) {
    global $admin_chat_id, $platform_fee;
    
    $welcome = "🏦 *WestBridge Escrow Bot*\n\n";
    $welcome .= "Hi! I help you trade securely with escrow protection.\n\n";
    $welcome .= "🔹 *How it works:*\n";
    $welcome .= "1️⃣ Buyer creates escrow with seller's details\n";
    $welcome .= "2️⃣ Buyer deposits crypto to our wallet\n";
    $welcome .= "3️⃣ Buyer receives goods from seller\n";
    $welcome .= "4️⃣ Buyer releases funds to seller\n\n";
    $welcome .= "💰 *Platform Fee:* {$platform_fee}% of transaction amount\n\n";
    $welcome .= "📌 *Supported Cryptos:* BTC, SOL, USDT, DOGE";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📝 New Escrow', 'callback_data' => 'new_escrow'],
                ['text' => '📋 My Escrows', 'callback_data' => 'my_escrows']
            ],
            [
                ['text' => '💰 Wallet Info', 'callback_data' => 'wallet_info'],
                ['text' => '❓ Help', 'callback_data' => 'help']
            ]
        ]
    ];
    
    if ($chat_id == $admin_chat_id) {
        $keyboard['inline_keyboard'][] = [
            ['text' => '👑 Admin Panel', 'callback_data' => 'admin_panel']
        ];
    }
    
    sendMessage($chat_id, $welcome, $keyboard);
}

// ============================================
// ESCROW CREATION FLOW
// ============================================
function showCryptoSelection($chat_id) {
    global $wallets;
    $text = "💰 *Select Crypto Currency*\n\n";
    $text .= "Choose the crypto you want to use for this escrow:";
    
    $buttons = [];
    $row = [];
    $i = 0;
    foreach ($wallets as $crypto => $address) {
        $row[] = ['text' => $crypto, 'callback_data' => "crypto_$crypto"];
        $i++;
        if ($i % 2 == 0) {
            $buttons[] = $row;
            $row = [];
        }
    }
    if (!empty($row)) {
        $buttons[] = $row;
    }
    $buttons[] = [['text' => '🔙 Back', 'callback_data' => 'back_menu']];
    
    $keyboard = ['inline_keyboard' => $buttons];
    sendMessage($chat_id, $text, $keyboard);
}

function showEscrowForm($chat_id, $crypto) {
    global $wallets;
    
    $text = "📝 *Create New Escrow*\n\n";
    $text .= "💰 *Crypto:* $crypto\n";
    $text .= "📌 *Deposit Address:*\n`{$wallets[$crypto]}`\n\n";
    $text .= "Please enter the seller's details using the buttons below:\n\n";
    $text .= "👤 *Seller Username:* _Not set_\n";
    $text .= "💰 *Seller Wallet:* _Not set_\n";
    $text .= "📝 *Description:* _Not set_\n\n";
    $text .= "📌 Click each button to enter the information.";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '👤 Seller Username', 'callback_data' => "enter_seller_$crypto"],
                ['text' => '💰 Seller Wallet', 'callback_data' => "enter_wallet_$crypto"]
            ],
            [
                ['text' => '📝 Description', 'callback_data' => "enter_desc_$crypto"]
            ],
            [
                ['text' => '✅ Create Escrow', 'callback_data' => "create_escrow_$crypto"],
                ['text' => '🔙 Back', 'callback_data' => 'new_escrow']
            ]
        ]
    ];
    
    sendMessage($chat_id, $text, $keyboard);
}

// ============================================
// ADMIN PANEL
// ============================================
function showAdminPanel($chat_id) {
    $escrows = loadEscrows();
    $pending = array_filter($escrows, function($e) { return $e['status'] == 'pending_payment'; });
    $paid = array_filter($escrows, function($e) { return $e['status'] == 'paid'; });
    $completed = array_filter($escrows, function($e) { return $e['status'] == 'completed'; });
    $users = loadUsers();
    
    $panel = "👑 *Admin Panel*\n\n";
    $panel .= "📊 *Statistics:*\n";
    $panel .= "• Total Users: " . count($users) . "\n";
    $panel .= "• Total Escrows: " . count($escrows) . "\n";
    $panel .= "• Pending Payment: " . count($pending) . "\n";
    $panel .= "• Paid: " . count($paid) . "\n";
    $panel .= "• Completed: " . count($completed) . "\n\n";
    $panel .= "📌 *Admin Actions:*\n";
    $panel .= "• Monitor all escrows";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📋 Pending', 'callback_data' => 'admin_pending'],
                ['text' => '💰 Paid', 'callback_data' => 'admin_paid']
            ],
            [
                ['text' => '📊 All Escrows', 'callback_data' => 'admin_all'],
                ['text' => '👥 All Users', 'callback_data' => 'admin_users']
            ],
            [
                ['text' => '🔙 Back', 'callback_data' => 'back_menu']
            ]
        ]
    ];
    
    sendMessage($chat_id, $panel, $keyboard);
}

// ============================================
// MAIN POLLING LOOP
// ============================================
set_time_limit(0);
$last_update_id = 0;

while (true) {
    $url = "https://api.telegram.org/bot$token/getUpdates?timeout=30&offset=" . ($last_update_id + 1);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    
    $updates = json_decode($response, true);
    
    if (isset($updates['result'])) {
        foreach ($updates['result'] as $update) {
            $last_update_id = $update['update_id'];
            
            // Handle CALLBACK QUERIES (button clicks)
            if (isset($update['callback_query'])) {
                $callback_id = $update['callback_query']['id'];
                $chat_id = $update['callback_query']['message']['chat']['id'];
                $data = $update['callback_query']['data'];
                $user = $update['callback_query']['from'];
                $user_id = $user['id'];
                $username = $user['username'] ?? '';
                $first_name = $user['first_name'] ?? '';
                
                registerUser($user_id, $username, $first_name);
                
                // ===== ADMIN PANEL - ONLY FOR ADMIN =====
                if ($data == 'admin_panel') {
                    if ($chat_id != $admin_chat_id) {
                        answerCallback($callback_id, "⛔ Admin access only!");
                        continue;
                    }
                    showAdminPanel($chat_id);
                    answerCallback($callback_id);
                    continue;
                }
                
                if (strpos($data, 'admin_') === 0) {
                    if ($chat_id != $admin_chat_id) {
                        answerCallback($callback_id, "⛔ Admin access only!");
                        continue;
                    }
                    
                    $escrows = loadEscrows();
                    
                    if ($data == 'admin_pending') {
                        $pending = array_filter($escrows, function($e) { return $e['status'] == 'pending_payment'; });
                        if (empty($pending)) {
                            sendMessage($chat_id, "📋 No pending escrows.");
                        } else {
                            $text = "📋 *Pending Escrows*\n\n";
                            foreach ($pending as $e) {
                                $text .= "🆔 #{$e['id']}\n💰 {$e['crypto']}\n👤 Buyer: @{$e['buyer_username']}\n👤 Seller: @{$e['seller_username']}\n📝 {$e['description']}\n📅 " . date('Y-m-d H:i', $e['created_at']) . "\n\n";
                            }
                            sendMessage($chat_id, $text);
                        }
                    } elseif ($data == 'admin_paid') {
                        $paid = array_filter($escrows, function($e) { return $e['status'] == 'paid'; });
                        if (empty($paid)) {
                            sendMessage($chat_id, "💰 No paid escrows.");
                        } else {
                            $text = "💰 *Paid Escrows*\n\n";
                            foreach ($paid as $e) {
                                $text .= "🆔 #{$e['id']}\n💰 {$e['crypto']}\n👤 Buyer: @{$e['buyer_username']}\n👤 Seller: @{$e['seller_username']}\n📅 Paid: " . date('Y-m-d H:i', $e['paid_at']) . "\n\n";
                            }
                            sendMessage($chat_id, $text);
                        }
                    } elseif ($data == 'admin_all') {
                        if (empty($escrows)) {
                            sendMessage($chat_id, "📋 No escrows found.");
                        } else {
                            $text = "📊 *All Escrows*\n\n";
                            foreach ($escrows as $e) {
                                $status_emoji = $e['status'] == 'pending_payment' ? '⏳' : ($e['status'] == 'paid' ? '💰' : '🎉');
                                $text .= "{$status_emoji} #{$e['id']} | {$e['crypto']} | {$e['status']}\n";
                            }
                            sendMessage($chat_id, $text);
                        }
                    } elseif ($data == 'admin_users') {
                        $users = loadUsers();
                        if (empty($users)) {
                            sendMessage($chat_id, "👥 No users registered.");
                        } else {
                            $text = "👥 *Registered Users*\n\n";
                            foreach ($users as $id => $user) {
                                $text .= "🆔 `$id`\n";
                                if (!empty($user['username'])) {
                                    $text .= "📛 @{$user['username']}\n";
                                }
                                $text .= "📅 " . date('Y-m-d H:i', $user['registered']) . "\n\n";
                            }
                            sendMessage($chat_id, $text);
                        }
                    }
                    answerCallback($callback_id);
                    continue;
                }
                
                // ===== CRYPTO SELECTION =====
                if (strpos($data, 'crypto_') === 0) {
                    $crypto = str_replace('crypto_', '', $data);
                    global $wallets;
                    if (isset($wallets[$crypto])) {
                        showEscrowForm($chat_id, $crypto);
                        $_SESSION['selected_crypto'] = $crypto;
                    }
                    answerCallback($callback_id);
                    continue;
                }
                
                // ===== ENTER SELLER USERNAME =====
                if (strpos($data, 'enter_seller_') === 0) {
                    $crypto = str_replace('enter_seller_', '', $data);
                    sendMessage($chat_id, "👤 *Enter Seller Username*\n\nPlease send the seller's Telegram username.\n\nExample: `@username`");
                    $_SESSION['state'] = 'awaiting_seller';
                    $_SESSION['crypto'] = $crypto;
                    answerCallback($callback_id);
                    continue;
                }
                
                // ===== ENTER SELLER WALLET =====
                if (strpos($data, 'enter_wallet_') === 0) {
                    $crypto = str_replace('enter_wallet_', '', $data);
                    sendMessage($chat_id, "💰 *Enter Seller Wallet Address*\n\nPlease send the seller's wallet address to receive funds in $crypto.");
                    $_SESSION['state'] = 'awaiting_wallet';
                    $_SESSION['crypto'] = $crypto;
                    answerCallback($callback_id);
                    continue;
                }
                
                // ===== ENTER DESCRIPTION =====
                if (strpos($data, 'enter_desc_') === 0) {
                    $crypto = str_replace('enter_desc_', '', $data);
                    sendMessage($chat_id, "📝 *Enter Description*\n\nPlease describe the goods or service in detail.");
                    $_SESSION['state'] = 'awaiting_desc';
                    $_SESSION['crypto'] = $crypto;
                    answerCallback($callback_id);
                    continue;
                }
                
                // ===== CREATE ESCROW =====
                if (strpos($data, 'create_escrow_') === 0) {
                    $crypto = str_replace('create_escrow_', '', $data);
                    
                    if (!isset($_SESSION['seller_username'])) {
                        sendMessage($chat_id, "❌ Please enter the seller's username first.");
                        answerCallback($callback_id);
                        continue;
                    }
                    
                    if (!isset($_SESSION['seller_wallet'])) {
                        sendMessage($chat_id, "❌ Please enter the seller's wallet address first.");
                        answerCallback($callback_id);
                        continue;
                    }
                    
                    if (!isset($_SESSION['description'])) {
                        sendMessage($chat_id, "❌ Please enter a description first.");
                        answerCallback($callback_id);
                        continue;
                    }
                    
                    $seller_username = $_SESSION['seller_username'];
                    $seller_wallet = $_SESSION['seller_wallet'];
                    $description = $_SESSION['description'];
                    $buyer_username = $username;
                    
                    $escrow_id = createEscrow($chat_id, $seller_username, $seller_wallet, $crypto, $description, $buyer_username);
                    
                    global $wallets;
                    $deposit_address = $wallets[$crypto];
                    
                    $confirmation = "✅ *Escrow Created!*\n\n";
                    $confirmation .= "🆔 *Escrow ID:* #$escrow_id\n";
                    $confirmation .= "💰 *Crypto:* $crypto\n";
                    $confirmation .= "👤 *Seller:* @$seller_username\n";
                    $confirmation .= "📌 *Seller Wallet:* `$seller_wallet`\n";
                    $confirmation .= "📝 *Description:* $description\n\n";
                    $confirmation .= "💰 *Deposit Address:*\n`$deposit_address`\n\n";
                    $confirmation .= "Send the exact amount of $crypto to this address.\n";
                    $confirmation .= "✅ Click 'Payment Sent' after depositing.\n";
                    $confirmation .= "📌 After receiving goods, click 'Release Funds to Seller'.";
                    
                    $keyboard = [
                        'inline_keyboard' => [
                            [
                                ['text' => '💰 Payment Sent', 'callback_data' => "payment_sent_$escrow_id"]
                            ],
                            [
                                ['text' => '🔓 Release Funds to Seller', 'callback_data' => "release_funds_$escrow_id"]
                            ],
                            [
                                ['text' => '📋 View Escrow', 'callback_data' => "view_escrow_$escrow_id"],
                                ['text' => '🔙 Menu', 'callback_data' => 'back_menu']
                            ]
                        ]
                    ];
                    
                    sendMessage($chat_id, $confirmation, $keyboard);
                    
                    // Try to notify seller
                    $seller_msg = "📝 *Escrow Created for You!*\n\n";
                    $seller_msg .= "🆔 #$escrow_id\n";
                    $seller_msg .= "💰 Crypto: $crypto\n";
                    $seller_msg .= "👤 Buyer: @$buyer_username\n";
                    $seller_msg .= "📝 Description: $description\n\n";
                    $seller_msg .= "📌 Your Wallet: `$seller_wallet`\n\n";
                    $seller_msg .= "🔹 *Wait for buyer to deposit and release funds to you.*";
                    
                    trySendMessage("@$seller_username", $seller_msg);
                    
                    // Notify admin
                    sendMessage($admin_chat_id, "📝 *New Escrow Created!*\n\n🆔 #$escrow_id\n💰 $crypto\n👤 Buyer: @$buyer_username\n👤 Seller: @$seller_username\n📌 Seller Wallet: `$seller_wallet`\n📝 $description\n\nWaiting for buyer to deposit.");
                    
                    // Clear session
                    unset($_SESSION['seller_username']);
                    unset($_SESSION['seller_wallet']);
                    unset($_SESSION['description']);
                    unset($_SESSION['state']);
                    
                    answerCallback($callback_id);
                    continue;
                }
                
                // ===== PAYMENT SENT (Buyer) =====
                if (strpos($data, 'payment_sent_') === 0) {
                    $escrow_id = intval(str_replace('payment_sent_', '', $data));
                    $escrow = getEscrow($escrow_id);
                    
                    if (!$escrow) {
                        sendMessage($chat_id, "❌ Escrow not found.");
                        answerCallback($callback_id);
                        continue;
                    }
                    
                    if ($escrow['status'] == 'paid') {
                        sendMessage($chat_id, "✅ Payment already confirmed.");
                        answerCallback($callback_id);
                        continue;
                    }
                    
                    if ($escrow['status'] == 'completed') {
                        sendMessage($chat_id, "✅ This escrow is already completed.");
                        answerCallback($callback_id);
                        continue;
                    }
                    
                    updateEscrowStatus($escrow_id, 'paid');
                    
                    sendMessage($chat_id, "✅ *Payment Confirmed!*\n\nThank you! Funds are now in escrow.\n\n📌 Wait for seller to deliver goods.\n📌 After receiving goods, click 'Release Funds to Seller'.");
                    
                    // Notify seller
                    $seller_msg = "💰 *Payment Confirmed!*\n\nBuyer has deposited funds for Escrow #$escrow_id.\n\n📌 Deliver your goods to the buyer.\n📌 Buyer will release funds to your wallet when received.";
                    
                    trySendMessage("@{$escrow['seller_username']}", $seller_msg);
                    
                    // Notify admin
                    sendMessage($admin_chat_id, "💰 *Payment Confirmed!*\n\n🆔 #$escrow_id\n💰 {$escrow['crypto']}\n👤 Buyer: @{$escrow['buyer_username']}\n👤 Seller: @{$escrow['seller_username']}\n📌 Seller Wallet: `{$escrow['seller_wallet']}`\n\nWaiting for buyer to release funds.");
                    
                    answerCallback($callback_id);
                    continue;
                }
                
                // ===== RELEASE FUNDS TO SELLER (Buyer) =====
                if (strpos($data, 'release_funds_') === 0) {
                    $escrow_id = intval(str_replace('release_funds_', '', $data));
                    $escrow = getEscrow($escrow_id);
                    
                    if (!$escrow) {
                        sendMessage($chat_id, "❌ Escrow not found.");
                        answerCallback($callback_id);
                        continue;
                    }
                    
                    if ($escrow['status'] == 'pending_payment') {
                        sendMessage($chat_id, "❌ Payment hasn't been confirmed yet. Please confirm payment first.");
                        answerCallback($callback_id);
                        continue;
                    }
                    
                    if ($escrow['status'] == 'completed') {
                        sendMessage($chat_id, "✅ Funds already released to seller.");
                        answerCallback($callback_id);
                        continue;
                    }
                    
                    updateEscrowStatus($escrow_id, 'completed');
                    
                    sendMessage($chat_id, "✅ *Funds Released!*\n\nFunds for Escrow #$escrow_id have been released to the seller!\n\n📌 Seller Wallet: `{$escrow['seller_wallet']}`\n💰 Crypto: {$escrow['crypto']}\n\nThank you for using WestBridge Escrow Bot!");
                    
                    // Notify seller
                    $seller_msg = "🎉 *Funds Released!*\n\nFunds for Escrow #$escrow_id have been released to your wallet!\n\n📌 Your Wallet: `{$escrow['seller_wallet']}`\n💰 Crypto: {$escrow['crypto']}\n\nCongratulations on your successful trade!";
                    
                    trySendMessage("@{$escrow['seller_username']}", $seller_msg);
                    
                    // Notify admin
                    sendMessage($admin_chat_id, "🎉 *Escrow Completed!*\n\n🆔 #$escrow_id\n💰 {$escrow['crypto']}\n👤 Buyer: @{$escrow['buyer_username']}\n👤 Seller: @{$escrow['seller_username']}\n📌 Seller Wallet: `{$escrow['seller_wallet']}`\n\nFunds released successfully!");
                    
                    answerCallback($callback_id);
                    continue;
                }
                
                // ===== VIEW ESCROW =====
                if (strpos($data, 'view_escrow_') === 0) {
                    $escrow_id = intval(str_replace('view_escrow_', '', $data));
                    $escrow = getEscrow($escrow_id);
                    
                    if (!$escrow) {
                        sendMessage($chat_id, "❌ Escrow not found.");
                    } else {
                        $status_emoji = $escrow['status'] == 'pending_payment' ? '⏳' : ($escrow['status'] == 'paid' ? '💰' : '🎉');
                        $text = "📋 *Escrow Details*\n\n";
                        $text .= "🆔 ID: #{$escrow['id']}\n";
                        $text .= "💰 Crypto: {$escrow['crypto']}\n";
                        $text .= "📊 Status: {$status_emoji} {$escrow['status']}\n";
                        $text .= "👤 Buyer: @{$escrow['buyer_username']}\n";
                        $text .= "👤 Seller: @{$escrow['seller_username']}\n";
                        $text .= "📌 Seller Wallet: `{$escrow['seller_wallet']}`\n";
                        $text .= "📝 Description: {$escrow['description']}\n";
                        $text .= "📅 Created: " . date('Y-m-d H:i', $escrow['created_at']);
                        if ($escrow['paid_at']) {
                            $text .= "\n💰 Paid: " . date('Y-m-d H:i', $escrow['paid_at']);
                        }
                        if ($escrow['completed_at']) {
                            $text .= "\n🎉 Completed: " . date('Y-m-d H:i', $escrow['completed_at']);
                        }
                        sendMessage($chat_id, $text);
                    }
                    answerCallback($callback_id);
                    continue;
                }
                
                // ===== OTHER CALLBACKS =====
                switch ($data) {
                    case 'back_menu':
                        showMainMenu($chat_id);
                        break;
                        
                    case 'new_escrow':
                        showCryptoSelection($chat_id);
                        break;
                        
                    case 'my_escrows':
                        $escrows = getUserEscrows($chat_id);
                        if (empty($escrows)) {
                            sendMessage($chat_id, "📋 You have no active escrows.");
                        } else {
                            $text = "📋 *Your Escrows*\n\n";
                            foreach ($escrows as $e) {
                                $status_emoji = $e['status'] == 'pending_payment' ? '⏳' : ($e['status'] == 'paid' ? '💰' : '🎉');
                                $text .= "{$status_emoji} #{$e['id']} - {$e['crypto']} - {$e['status']}\n";
                            }
                            sendMessage($chat_id, $text);
                        }
                        break;
                        
                    case 'wallet_info':
                        global $wallets;
                        $text = "💰 *Wallet Addresses*\n\n";
                        foreach ($wallets as $crypto => $address) {
                            $text .= "• *$crypto:*\n`$address`\n\n";
                        }
                        $text .= "📌 Send funds to these addresses for escrow deposits.";
                        sendMessage($chat_id, $text);
                        break;
                        
                    case 'help':
                        $help = "❓ *Help & Commands*\n\n";
                        $help .= "📝 `/start` - Main menu\n";
                        $help .= "📝 'New Escrow' - Create a trade\n";
                        $help .= "📋 'My Escrows' - View your trades\n";
                        $help .= "💰 'Wallet Info' - Deposit addresses\n\n";
                        $help .= "🔹 *Admin Commands:*\n";
                        $help .= "• Admin Panel - Monitor all escrows";
                        sendMessage($chat_id, $help);
                        break;
                        
                    default:
                        answerCallback($callback_id, "🔄 Loading...");
                }
                
                answerCallback($callback_id);
            }
            
            // ===== HANDLE TEXT MESSAGES =====
            if (isset($update['message'])) {
                $chat_id = $update['message']['chat']['id'];
                $message = trim($update['message']['text'] ?? '');
                $user = $update['message']['from'];
                $user_id = $user['id'];
                $username = $user['username'] ?? '';
                $first_name = $user['first_name'] ?? '';
                
                registerUser($user_id, $username, $first_name);
                
                // Handle /start
                if ($message == '/start') {
                    showMainMenu($chat_id);
                    continue;
                }
                
                // ===== BUYER ENTERING SELLER USERNAME =====
                if (isset($_SESSION['state']) && $_SESSION['state'] == 'awaiting_seller') {
                    $seller_username = ltrim($message, '@');
                    if (!empty($seller_username)) {
                        $_SESSION['seller_username'] = $seller_username;
                        sendMessage($chat_id, "✅ Seller username set: @$seller_username\n\nNow enter the Seller's Wallet Address.");
                        $_SESSION['state'] = 'awaiting_wallet';
                    } else {
                        sendMessage($chat_id, "❌ Invalid username. Please enter a valid Telegram username.\nExample: `@username`");
                    }
                    continue;
                }
                
                // ===== BUYER ENTERING SELLER WALLET =====
                if (isset($_SESSION['state']) && $_SESSION['state'] == 'awaiting_wallet') {
                    if (strlen($message) > 10) {
                        $_SESSION['seller_wallet'] = $message;
                        sendMessage($chat_id, "✅ Seller wallet set.\n\nNow enter the Description.");
                        $_SESSION['state'] = 'awaiting_desc';
                    } else {
                        $crypto = $_SESSION['crypto'] ?? '';
                        sendMessage($chat_id, "❌ Invalid wallet address. Please enter a valid $crypto wallet address.");
                    }
                    continue;
                }
                
                // ===== BUYER ENTERING DESCRIPTION =====
                if (isset($_SESSION['state']) && $_SESSION['state'] == 'awaiting_desc') {
                    if (strlen($message) > 5) {
                        $_SESSION['description'] = $message;
                        $crypto = $_SESSION['crypto'];
                        
                        $text = "📝 *Escrow Summary*\n\n";
                        $text .= "💰 *Crypto:* $crypto\n";
                        $text .= "👤 *Seller:* @{$_SESSION['seller_username']}\n";
                        $text .= "💰 *Seller Wallet:* `{$_SESSION['seller_wallet']}`\n";
                        $text .= "📝 *Description:* $message\n\n";
                        $text .= "✅ Ready to create escrow?";
                        
                        $keyboard = [
                            'inline_keyboard' => [
                                [
                                    ['text' => '✅ Create Escrow', 'callback_data' => "create_escrow_$crypto"],
                                    ['text' => '🔙 Back', 'callback_data' => 'new_escrow']
                                ]
                            ]
                        ];
                        
                        sendMessage($chat_id, $text, $keyboard);
                        $_SESSION['state'] = 'completed';
                    } else {
                        sendMessage($chat_id, "❌ Description is too short. Please provide more details.");
                    }
                    continue;
                }
                
                // Unknown command
                if ($message != '') {
                    sendMessage($chat_id, "❌ Unknown command. Use /start to see available options.");
                }
            }
        }
    }
    
    sleep(1);
}
?>