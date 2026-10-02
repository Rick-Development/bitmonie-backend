<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\P2PDisclaimer;

class P2PDisclaimerSeeder extends Seeder
{
    public function run()
    {
        $disclaimers = [
            [
                'key' => 'welcome',
                'title' => 'Welcome to the P2P Marketplace',
                'content' => 'Welcome to our Peer-to-Peer (P2P) Marketplace — a secure escrow-based environment that enables users to buy and sell digital assets directly with one another using local payment methods.

All trades are protected by platform-managed escrow, ensuring that digital assets are only released when trade conditions are successfully met. Please read all instructions carefully and follow platform guidelines to ensure a smooth and secure trading experience.

By continuing, you acknowledge that you understand how P2P trading works and agree to comply with all platform rules.',
                'type' => 'info',
                'requires_acceptance' => false,
                'is_active' => true,
            ],
            [
                'key' => 'first_time',
                'title' => 'Important Notice – Please Read Carefully',
                'content' => 'This P2P marketplace facilitates direct transactions between users. The platform does not process, hold, or control fiat funds involved in P2P trades.

You acknowledge and agree that:
• Fiat payments are made directly between users
• The platform acts solely as an escrow service provider for digital assets
• You are responsible for verifying payment details before transferring funds
• Any action taken outside the platform may result in loss of funds and is done at your own risk

Proceed only if you fully understand the P2P trading process.',
                'type' => 'warning',
                'requires_acceptance' => true,
                'is_active' => true,
            ],
            [
                'key' => 'buyer_confirm',
                'title' => 'Buyer Confirmation Required',
                'content' => 'Before proceeding with this trade, please note the following:

• You must transfer funds only to the payment method displayed on this order
• Do not mark the order as "Payment Sent" unless you have completed the transfer
• Transfers made outside the specified payment details are not protected
• Marking "Payment Sent" without completing payment may result in account suspension

Ensure that the account name matches the seller\'s verified details before making payment.',
                'type' => 'warning',
                'requires_acceptance' => false,
                'is_active' => true,
            ],
            [
                'key' => 'seller_confirm',
                'title' => 'Seller Confirmation Required',
                'content' => 'You are about to release digital assets from escrow.

Please confirm that:
• You have received full payment in your bank or wallet
• The payment is irreversible and cleared
• The sender\'s name matches the buyer\'s registered information

Once released, this action cannot be reversed. The platform will not be liable for any loss resulting from premature release.',
                'type' => 'critical',
                'requires_acceptance' => false,
                'is_active' => true,
            ],
            [
                'key' => 'payment_warning',
                'title' => 'Payment Confirmation Warning',
                'content' => '⚠️ Do NOT click "Payment Received" unless you have confirmed receipt of funds in your account. False confirmation may result in permanent loss of assets.',
                'type' => 'critical',
                'requires_acceptance' => false,
                'is_active' => true,
            ],
            [
                'key' => 'dispute_notice',
                'title' => 'Dispute Resolution Notice',
                'content' => 'In the event of a dispute, the platform reserves the right to review all submitted evidence, including payment proofs, chat records, and transaction history.

All dispute resolutions are final and binding. Users found to be engaging in fraudulent or misleading behavior may face account restrictions, asset forfeiture, or permanent suspension.',
                'type' => 'warning',
                'requires_acceptance' => false,
                'is_active' => true,
            ],
            [
                'key' => 'anti_fraud',
                'title' => 'Security Notice',
                'content' => 'For your protection:
• Do not communicate or transact outside the platform
• Do not share personal contact details
• Do not accept screenshots or verbal payment claims as proof

The platform will not be responsible for losses arising from off-platform arrangements.',
                'type' => 'warning',
                'requires_acceptance' => false,
                'is_active' => true,
            ],
            [
                'key' => 'merchant_notice',
                'title' => 'Advertiser Responsibility Notice',
                'content' => 'By creating advertisements on this platform, you confirm that:

• All payment methods provided are legally owned by you
• You will respond to orders within the specified time
• You will not request off-platform payments or additional fees

Violation of advertiser rules may result in ad removal, escrow suspension, or permanent account restrictions.',
                'type' => 'warning',
                'requires_acceptance' => false,
                'is_active' => true,
            ],
            [
                'key' => 'compliance',
                'title' => 'Compliance & Jurisdiction Disclaimer',
                'content' => 'P2P trading involves financial risk. Users are responsible for ensuring compliance with local laws and regulations applicable in their jurisdiction. The platform does not provide financial, legal, or investment advice.',
                'type' => 'info',
                'requires_acceptance' => false,
                'is_active' => true,
            ],
            [
                'key' => 'payment_received_warning',
                'title' => 'Critical: Verify Payment Before Release',
                'content' => '⚠️ CRITICAL WARNING ⚠️

Do NOT release crypto unless:
✓ Funds are visible in YOUR account
✓ Payment is IRREVERSIBLE
✓ Sender name MATCHES buyer profile

Premature release = PERMANENT LOSS. No refunds.',
                'type' => 'critical',
                'requires_acceptance' => false,
                'is_active' => true,
            ],
        ];

        foreach ($disclaimers as $disclaimer) {
            P2PDisclaimer::updateOrCreate(
                ['key' => $disclaimer['key']],
                $disclaimer
            );
        }
    }
}
