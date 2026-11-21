<?php

// generated — do not edit
// Source: openapi/merchant.v1.json (composer generate)

declare(strict_types=1);

namespace Zeny\Generated;

/**
 * PHPStan shapes of the Merchant API v1 contract.
 *
 * @phpstan-type S_Error array{
 *     type: string,
 *     code: string,
 *     message: string,
 *     param: string|null,
 *     details: list<S_ErrorDetail>,
 *     requestId: string|null,
 * }
 * @phpstan-type S_ErrorDetail array{
 *     param: string|null,
 *     code: string,
 *     message: string,
 *     id: string|null,
 * }
 * @phpstan-type S_merchant_balance_v1_CreateTestFundsRequest array{
 *     amount: S_type_v1_MoneyInput,
 * }
 * @phpstan-type S_merchant_balance_v1_TestFunds array{
 *     id: string,
 *     object: string,
 *     amount: S_type_v1_Money|null,
 *     createdAt: string|null,
 * }
 * @phpstan-type S_merchant_payment_v1_CardMethod array<string, mixed>
 * @phpstan-type S_merchant_payment_v1_CreatePaymentRequest array{
 *     reference: string,
 *     amount: S_type_v1_MoneyInput,
 *     description?: string,
 *     payer?: S_merchant_payment_v1_PayerInput,
 *     metadata?: array<string, string>,
 *     expiresAt?: string,
 * }
 * @phpstan-type S_merchant_payment_v1_Dispute array{
 *     id: string,
 *     object: string,
 *     payment: string,
 *     type: string,
 *     status: string,
 *     amount: S_type_v1_Money|null,
 *     frozenAmount: S_type_v1_Money|null,
 *     shortfallAmount: S_type_v1_Money|null,
 *     openedAt: string|null,
 *     resolvedAt: string|null,
 * }
 * @phpstan-type S_merchant_payment_v1_DisputeEvent array{
 *     id: string,
 *     object: string,
 *     type: string,
 *     apiVersion: string,
 *     createdAt: string,
 *     mode: string,
 *     resourceVersion: int,
 *     data: S_merchant_payment_v1_DisputeEvent_Data,
 * }
 * @phpstan-type S_merchant_payment_v1_DisputeEvent_Data array{
 *     object: S_merchant_payment_v1_Dispute,
 * }
 * @phpstan-type S_merchant_payment_v1_DisputeSummary array{
 *     id: string,
 *     object: string,
 *     type: string,
 *     status: string,
 *     amount: S_type_v1_Money|null,
 *     openedAt: string|null,
 *     resolvedAt: string|null,
 * }
 * @phpstan-type S_merchant_payment_v1_EwalletMethod array{
 *     provider: string,
 * }
 * @phpstan-type S_merchant_payment_v1_Fee array{
 *     type: string,
 *     amount: S_type_v1_Money|null,
 * }
 * @phpstan-type S_merchant_payment_v1_ListDisputesResponse array{
 *     object: string,
 *     data: list<S_merchant_payment_v1_Dispute>,
 *     hasMore: bool,
 *     nextCursor: string,
 * }
 * @phpstan-type S_merchant_payment_v1_Payer array{
 *     name: string,
 *     email: string,
 *     phone: string,
 * }
 * @phpstan-type S_merchant_payment_v1_PayerInput array{
 *     name?: string,
 *     email?: string,
 *     phone?: string,
 * }
 * @phpstan-type S_merchant_payment_v1_Payment array{
 *     id: string,
 *     object: string,
 *     reference: string,
 *     amount: S_type_v1_Money|null,
 *     amountReceived: S_type_v1_Money|null,
 *     amountRefunded: S_type_v1_Money|null,
 *     status: string,
 *     captureMethod: string,
 *     description: string,
 *     payer: S_merchant_payment_v1_Payer|null,
 *     metadata: array<string, string>,
 *     latestAttempt: S_merchant_payment_v1_PaymentAttempt|null,
 *     fees: list<S_merchant_payment_v1_Fee>,
 *     net: S_type_v1_Money|null,
 *     settlement: S_merchant_payment_v1_Settlement|null,
 *     disputes: list<S_merchant_payment_v1_DisputeSummary>,
 *     paymentLink: string,
 *     createdAt: string|null,
 *     paidAt: string|null,
 *     expiresAt: string|null,
 * }
 * @phpstan-type S_merchant_payment_v1_PaymentAttempt array{
 *     id: string,
 *     object: string,
 *     payment: string,
 *     status: string,
 *     paymentMethod: S_merchant_payment_v1_PaymentMethod|null,
 *     charge: S_type_v1_Money|null,
 *     nextAction: S_type_v1_NextAction|null,
 *     failure: S_type_v1_Failure|null,
 *     createdAt: string|null,
 *     expiresAt: string|null,
 *     uniqueCode: S_type_v1_Money|null,
 * }
 * @phpstan-type S_merchant_payment_v1_PaymentAttemptEvent array{
 *     id: string,
 *     object: string,
 *     type: string,
 *     apiVersion: string,
 *     createdAt: string,
 *     mode: string,
 *     resourceVersion: int,
 *     data: S_merchant_payment_v1_PaymentAttemptEvent_Data,
 * }
 * @phpstan-type S_merchant_payment_v1_PaymentAttemptEvent_Data array{
 *     object: S_merchant_payment_v1_PaymentAttempt,
 * }
 * @phpstan-type S_merchant_payment_v1_PaymentEvent array{
 *     id: string,
 *     object: string,
 *     type: string,
 *     apiVersion: string,
 *     createdAt: string,
 *     mode: string,
 *     resourceVersion: int,
 *     data: S_merchant_payment_v1_PaymentEvent_Data,
 * }
 * @phpstan-type S_merchant_payment_v1_PaymentEvent_Data array{
 *     object: S_merchant_payment_v1_Payment,
 * }
 * @phpstan-type S_merchant_payment_v1_PaymentMethod array{
 *     type: string,
 *     instrument: string,
 *     va: S_merchant_payment_v1_VaMethod|null,
 *     qris: S_merchant_payment_v1_QrisMethod|null,
 *     ewallet: S_merchant_payment_v1_EwalletMethod|null,
 *     card: S_merchant_payment_v1_CardMethod|null,
 * }
 * @phpstan-type S_merchant_payment_v1_QrisMethod array<string, mixed>
 * @phpstan-type S_merchant_payment_v1_Settlement array{
 *     status: string,
 *     dueAt: string|null,
 *     settledAt: string|null,
 * }
 * @phpstan-type S_merchant_payment_v1_VaMethod array{
 *     bank: string,
 * }
 * @phpstan-type S_merchant_payout_v1_BankAccountDestination array{
 *     bank: string,
 *     accountNumberLast4: string,
 * }
 * @phpstan-type S_merchant_payout_v1_CreatePayoutRequest array{
 *     reference: string,
 *     beneficiary: string,
 *     amount: S_type_v1_MoneyInput,
 *     description?: string,
 * }
 * @phpstan-type S_merchant_payout_v1_Destination array{
 *     type: string,
 *     holderName: string,
 *     bankAccount: S_merchant_payout_v1_BankAccountDestination|null,
 *     ewallet: S_merchant_payout_v1_EwalletDestination|null,
 * }
 * @phpstan-type S_merchant_payout_v1_EwalletDestination array{
 *     provider: string,
 *     accountNumberLast4: string,
 * }
 * @phpstan-type S_merchant_payout_v1_Fee array{
 *     type: string,
 *     amount: S_type_v1_Money|null,
 * }
 * @phpstan-type S_merchant_payout_v1_Payout array{
 *     id: string,
 *     object: string,
 *     reference: string,
 *     amount: S_type_v1_Money|null,
 *     fees: list<S_merchant_payout_v1_Fee>,
 *     net: S_type_v1_Money|null,
 *     debit: S_type_v1_Money|null,
 *     beneficiary: string,
 *     destination: S_merchant_payout_v1_Destination|null,
 *     status: string,
 *     failure: S_type_v1_Failure|null,
 *     description: string,
 *     createdAt: string|null,
 *     paidAt: string|null,
 *     type: string,
 * }
 * @phpstan-type S_merchant_payout_v1_PayoutEvent array{
 *     id: string,
 *     object: string,
 *     type: string,
 *     apiVersion: string,
 *     createdAt: string,
 *     mode: string,
 *     resourceVersion: int,
 *     data: S_merchant_payout_v1_PayoutEvent_Data,
 * }
 * @phpstan-type S_merchant_payout_v1_PayoutEvent_Data array{
 *     object: S_merchant_payout_v1_Payout,
 * }
 * @phpstan-type S_type_v1_DisplayQr array{
 *     data: string,
 * }
 * @phpstan-type S_type_v1_DisplayVa array{
 *     bank: string,
 *     number: string,
 *     billerCode: string,
 * }
 * @phpstan-type S_type_v1_Failure array{
 *     code: string,
 *     message: string,
 * }
 * @phpstan-type S_type_v1_Money array{
 *     value: string,
 *     currency: string,
 * }
 * @phpstan-type S_type_v1_MoneyInput array{
 *     value?: string,
 *     currency?: string,
 * }
 * @phpstan-type S_type_v1_NextAction array{
 *     type: string,
 *     displayVa: S_type_v1_DisplayVa|null,
 *     displayQr: S_type_v1_DisplayQr|null,
 *     redirect: S_type_v1_Redirect|null,
 *     openApp: S_type_v1_OpenApp|null,
 * }
 * @phpstan-type S_type_v1_OpenApp array{
 *     url: string,
 * }
 * @phpstan-type S_type_v1_Redirect array{
 *     url: string,
 * }
 * @phpstan-type Money S_type_v1_Money
 * @phpstan-type Payment S_merchant_payment_v1_Payment
 * @phpstan-type PaymentAttempt S_merchant_payment_v1_PaymentAttempt
 * @phpstan-type Payout S_merchant_payout_v1_Payout
 * @phpstan-type Dispute S_merchant_payment_v1_Dispute
 * @phpstan-type DisputeList S_merchant_payment_v1_ListDisputesResponse
 * @phpstan-type TestFunds S_merchant_balance_v1_TestFunds
 * @phpstan-type CreatePaymentParams S_merchant_payment_v1_CreatePaymentRequest
 * @phpstan-type CreatePayoutParams S_merchant_payout_v1_CreatePayoutRequest
 * @phpstan-type CreateTestFundsParams S_merchant_balance_v1_CreateTestFundsRequest
 * @phpstan-type ApiErrorBody S_Error
 * @phpstan-type PaymentEvent S_merchant_payment_v1_PaymentEvent
 * @phpstan-type PaymentAttemptEvent S_merchant_payment_v1_PaymentAttemptEvent
 * @phpstan-type PayoutEvent S_merchant_payout_v1_PayoutEvent
 * @phpstan-type DisputeEvent S_merchant_payment_v1_DisputeEvent
 * @phpstan-type CreatePaymentAttemptParams array{
 *     instrument: string,
 *     returnUrl?: string,
 * }
 * @phpstan-type ListDisputesParams array{
 *     status?: ''|'open'|'won'|'lost',
 *     limit?: int,
 *     cursor?: string,
 * }
 * @phpstan-type ZenyEventType 'dispute.closed'|'dispute.opened'|'payment.expired'|'payment.paid'|'payment_attempt.failed'|'payout.canceled'|'payout.failed'|'payout.paid'|'payout.rejected'
 * @phpstan-type ZenyEvent array{
 *     id: string,
 *     object: string,
 *     type: 'dispute.closed'|'dispute.opened',
 *     apiVersion: string,
 *     createdAt: string,
 *     mode: string,
 *     resourceVersion: int,
 *     data: S_merchant_payment_v1_DisputeEvent_Data,
 * }|array{
 *     id: string,
 *     object: string,
 *     type: 'payment.expired'|'payment.paid',
 *     apiVersion: string,
 *     createdAt: string,
 *     mode: string,
 *     resourceVersion: int,
 *     data: S_merchant_payment_v1_PaymentEvent_Data,
 * }|array{
 *     id: string,
 *     object: string,
 *     type: 'payment_attempt.failed',
 *     apiVersion: string,
 *     createdAt: string,
 *     mode: string,
 *     resourceVersion: int,
 *     data: S_merchant_payment_v1_PaymentAttemptEvent_Data,
 * }|array{
 *     id: string,
 *     object: string,
 *     type: 'payout.canceled'|'payout.failed'|'payout.paid'|'payout.rejected',
 *     apiVersion: string,
 *     createdAt: string,
 *     mode: string,
 *     resourceVersion: int,
 *     data: S_merchant_payout_v1_PayoutEvent_Data,
 * }
 */
final class MerchantV1 {}
