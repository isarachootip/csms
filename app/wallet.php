<?php
require_once __DIR__ . '/includes/app_config.php';
require_once __DIR__ . '/includes/app_layout.php';
capp_require_auth();

$cid  = capp_customer_id();
$cust = capp_customer();

$wallet = DB::fetchOne("SELECT * FROM wallet_accounts WHERE customer_id=?", [$cid]);
if (!$wallet) {
    DB::insert("INSERT INTO wallet_accounts (customer_id,balance) VALUES (?,0)", [$cid]);
    $wallet = DB::fetchOne("SELECT * FROM wallet_accounts WHERE customer_id=?", [$cid]);
}

// ── Top-up (simulate)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'topup') {
    $amount = (float)($_POST['amount'] ?? 0);
    $method = $_POST['method'] ?? 'promptpay';
    if ($amount >= 20 && $amount <= 10000) {
        $newBal = (float)$wallet['balance'] + $amount;
        DB::execute("UPDATE wallet_accounts SET balance=? WHERE id=?", [$newBal, $wallet['id']]);
        DB::insert("INSERT INTO wallet_transactions (wallet_id,type,amount,balance_after,description) VALUES (?,?,?,?,?)",
            [$wallet['id'], 'topup', $amount, $newBal, "เติมเงินผ่าน " . ($method==='promptpay'?'PromptPay':'Credit Card')]);
        DB::insert("INSERT INTO customer_notifications (customer_id,type,title,body,icon) VALUES (?,?,?,?,?)",
            [$cid,'wallet','เติมเงินสำเร็จ ✅',"เติมเงิน ฿{$amount} ยอดคงเหลือ ฿{$newBal}",'account_balance_wallet']);
        capp_flash('wallet', "เติมเงิน ฿" . number_format($amount,2) . " สำเร็จ!");
        header('Location: wallet.php'); exit;
    } else {
        capp_flash('wallet', 'ยอดเงินต้องอยู่ระหว่าง ฿20 - ฿10,000', 'error');
        header('Location: wallet.php'); exit;
    }
}

// ── Transaction History
$history = DB::fetchAll(
    "SELECT * FROM wallet_transactions WHERE wallet_id=? ORDER BY created_at DESC LIMIT 30",
    [$wallet['id']]
);

$balance = (float)$wallet['balance'];
$totalTopup = array_sum(array_column(array_filter($history, fn($r) => $r['type']==='topup'), 'amount'));
$totalSpend = array_sum(array_column(array_filter($history, fn($r) => $r['type']==='charge'), 'amount'));

$flash = capp_flash_html('wallet');
capp_head('กระเป๋าเงิน');
?>

<div class="page-content">
    <?php capp_top_bar('กระเป๋าเงิน', true); ?>

    <div class="px-4 pt-3 space-y-4">
        <?= $flash ?>

        <!-- ── Balance Hero Card ── -->
        <div class="rounded-2xl p-6 card-glow"
             style="background:linear-gradient(135deg,#1e3a6e 0%,#0f2a55 50%,#162952 100%);border:1px solid rgba(59,130,246,.3)">
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2">
                    <span class="material-icons-round text-yellow-400">account_balance_wallet</span>
                    <span class="text-sm text-blue-300 font-semibold">ยอดคงเหลือ</span>
                </div>
                <span class="text-xs text-gray-400"><?= $wallet['currency'] ?></span>
            </div>
            <p class="text-4xl font-bold text-white mb-4">฿<?= number_format($balance, 2) ?></p>
            <div class="grid grid-cols-2 gap-3 text-center text-xs">
                <div class="bg-green-900/30 rounded-xl py-2">
                    <p class="text-gray-400">เติมรวม</p>
                    <p class="font-bold text-green-300">฿<?= number_format($totalTopup,2) ?></p>
                </div>
                <div class="bg-red-900/30 rounded-xl py-2">
                    <p class="text-gray-400">ใช้รวม</p>
                    <p class="font-bold text-red-300">฿<?= number_format($totalSpend,2) ?></p>
                </div>
            </div>
        </div>

        <!-- ── Top-up Section ── -->
        <div class="glass rounded-2xl p-5 card-glow">
            <p class="text-sm font-bold text-white mb-4 flex items-center gap-2">
                <span class="material-icons-round text-green-400">add_circle</span>
                เติมเงิน
            </p>
            <form method="POST" id="topupForm">
                <input type="hidden" name="action" value="topup">
                <!-- Quick amounts -->
                <div class="grid grid-cols-4 gap-2 mb-4">
                    <?php foreach ([100,200,500,1000] as $a): ?>
                    <button type="button" onclick="setTopup(<?=$a?>)"
                            class="topup-btn py-3 rounded-xl text-sm font-bold border border-blue-700/50 text-blue-200 hover:border-yellow-500 hover:text-yellow-400 transition">
                        ฿<?= number_format($a) ?>
                    </button>
                    <?php endforeach; ?>
                </div>
                <div class="relative mb-4">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-yellow-400 font-bold">฿</span>
                    <input type="number" name="amount" id="topupAmt" min="20" max="10000" placeholder="กรอกยอด (20–10,000)"
                           class="input-field w-full pl-8 pr-4 py-3 rounded-xl text-sm">
                </div>

                <!-- Payment Method -->
                <p class="text-xs text-gray-400 mb-2 font-semibold">เลือกวิธีชำระ</p>
                <div class="space-y-2 mb-4">
                    <label class="flex items-center gap-3 p-3 rounded-xl border border-blue-700/40 cursor-pointer hover:border-yellow-500/60 transition has-[:checked]:border-yellow-500 has-[:checked]:bg-yellow-900/10">
                        <input type="radio" name="method" value="promptpay" checked class="accent-yellow-400">
                        <span class="text-xl">🏦</span>
                        <div class="flex-1">
                            <p class="text-sm font-semibold text-white">PromptPay / QR Code</p>
                            <p class="text-xs text-gray-400">สแกน QR ผ่าน Mobile Banking</p>
                        </div>
                    </label>
                    <label class="flex items-center gap-3 p-3 rounded-xl border border-blue-700/40 cursor-pointer hover:border-blue-500/60 transition has-[:checked]:border-blue-500 has-[:checked]:bg-blue-900/10">
                        <input type="radio" name="method" value="credit_card" class="accent-blue-400">
                        <span class="text-xl">💳</span>
                        <div>
                            <p class="text-sm font-semibold text-white">บัตรเครดิต/เดบิต</p>
                            <p class="text-xs text-gray-400">Visa, Mastercard</p>
                        </div>
                    </label>
                </div>

                <!-- Simulate PromptPay QR -->
                <div id="qrSection" class="hidden mb-4">
                    <div class="glass rounded-xl p-4 text-center">
                        <p class="text-xs text-gray-400 mb-3">สแกน QR Code เพื่อชำระเงิน</p>
                        <!-- Mock QR (in production: use PromptPay QR library) -->
                        <div class="w-40 h-40 bg-white rounded-xl mx-auto flex items-center justify-center mb-3">
                            <div class="text-center">
                                <p class="text-gray-800 text-xs font-bold">PromptPay QR</p>
                                <p class="text-gray-600 text-xs mt-1" id="qrAmount">฿0</p>
                                <div class="grid grid-cols-5 gap-0.5 mt-2">
                                    <?php for($i=0;$i<25;$i++): ?>
                                    <div class="w-3 h-3 rounded-sm <?= rand(0,1)?'bg-gray-900':'bg-white border border-gray-300' ?>"></div>
                                    <?php endfor; ?>
                                </div>
                                <p class="text-gray-500 text-xs mt-2">Mock QR</p>
                            </div>
                        </div>
                        <p class="text-xs text-yellow-400">⚠️ Demo Mode — กด "ยืนยัน" เพื่อเติมเงิน</p>
                    </div>
                </div>

                <button type="button" onclick="handleTopup()"
                        class="btn-primary w-full py-4 rounded-2xl text-lg font-bold">
                    เติมเงิน
                </button>
                <button type="submit" id="submitTopup" class="hidden"></button>
            </form>
        </div>

        <!-- ── Transaction History ── -->
        <div>
            <p class="text-sm font-bold text-white mb-3 flex items-center gap-2">
                <span class="material-icons-round text-blue-400">receipt_long</span>
                ประวัติการใช้ Wallet
            </p>
            <?php if (empty($history)): ?>
            <div class="glass rounded-xl py-10 text-center text-gray-500">
                <span class="material-icons-round text-4xl block mb-2">receipt_long</span>
                ยังไม่มีประวัติการใช้งาน
            </div>
            <?php else: ?>
            <div class="space-y-2">
                <?php foreach ($history as $h): ?>
                <?php
                    $isIn  = in_array($h['type'], ['topup','reward','refund']);
                    $icon  = match($h['type']) {'topup'=>'add_circle','charge'=>'bolt','refund'=>'replay','reward'=>'star',default=>'swap_horiz'};
                    $color = $isIn ? 'text-green-400' : 'text-red-400';
                    $sign  = $isIn ? '+' : '-';
                ?>
                <div class="glass rounded-xl px-4 py-3 flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0
                                <?= $isIn ? 'bg-green-900/40' : 'bg-red-900/30' ?>">
                        <span class="material-icons-round text-base <?= $isIn ? 'text-green-400' : 'text-red-400' ?>"><?= $icon ?></span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-white truncate">
                            <?= match($h['type']) {'topup'=>'เติมเงิน','charge'=>'ชาร์จรถ','refund'=>'คืนเงิน','reward'=>'รางวัล',default=>$h['type']} ?>
                        </p>
                        <p class="text-xs text-gray-400 truncate"><?= htmlspecialchars($h['description'] ?? '') ?></p>
                        <p class="text-xs text-gray-600"><?= ago($h['created_at']) ?></p>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <p class="font-bold <?= $color ?>"><?= $sign ?>฿<?= number_format(abs((float)$h['amount']),2) ?></p>
                        <p class="text-xs text-gray-500">฿<?= number_format((float)$h['balance_after'],2) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php capp_bottom_nav('profile'); ?>

<script>
function setTopup(a) {
    document.getElementById('topupAmt').value = a;
    document.querySelectorAll('.topup-btn').forEach(b=>{
        const match = b.textContent.trim()==='฿'+a.toLocaleString();
        b.classList.toggle('border-yellow-500',match);
        b.classList.toggle('text-yellow-400',match);
    });
}
function handleTopup() {
    const amt = parseFloat(document.getElementById('topupAmt').value||0);
    const method = document.querySelector('input[name="method"]:checked')?.value;
    if (!amt || amt < 20) { alert('กรุณากรอกยอดเงินอย่างน้อย ฿20'); return; }
    if (method === 'promptpay') {
        document.getElementById('qrSection').classList.remove('hidden');
        document.getElementById('qrAmount').textContent = '฿'+amt.toFixed(2);
        setTimeout(()=>{ if (confirm('ยืนยันการเติมเงิน ฿'+amt.toFixed(2)+' ผ่าน PromptPay?'))
            document.getElementById('submitTopup').click(); }, 500);
    } else {
        if (confirm('ยืนยันการเติมเงิน ฿'+amt.toFixed(2)+' ผ่านบัตร?'))
            document.getElementById('submitTopup').click();
    }
}
</script>

<?php capp_foot(); ?>
