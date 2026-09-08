export type PlanCode = 'basic' | 'premium' | 'ultra' | 'custom';

export type SubscriptionStatus =
    | 'trial'
    | 'active'
    | 'past_due'
    | 'suspended'
    | 'canceled';

export type OrganizationSummary = {
    id: number;
    name: string;
    slug: string;
};

export type OrganizationSubscription = {
    status: SubscriptionStatus;
    statusLabel: string;
    planCode: PlanCode;
    planName: string;
    isCustom: boolean;
    trialEndsAt: string | null;
    currentPeriodEnd: string | null;
    canceledAt: string | null;
    pendingPlanCode: PlanCode | null;
    pendingPlanName: string | null;
};

export type OrganizationQuota = {
    storeCount: number;
    maxStores: number | null;
    isFull: boolean;
};

export type OrganizationStore = {
    id: number;
    name: string;
    slug: string;
    isPersonal: boolean;
};

export type OrganizationPlan = {
    code: PlanCode;
    name: string;
    maxStores: number | null;
    priceMonthly: string | null;
    priceYearly: string | null;
    isCustom: boolean;
    isCurrent: boolean;
    isDowngrade: boolean;
};

export type CustomPlanRequestStatus = 'pending' | 'approved' | 'rejected';

export type OrganizationMember = {
    userId: number;
    name: string;
    email: string;
    role: 'owner' | 'manager';
    roleLabel: string;
    isSelf: boolean;
};

export type OrganizationPendingInvitation = {
    id: number;
    email: string;
    role: 'owner' | 'manager';
    roleLabel: string;
};

export type OrganizationInvoiceStatus =
    | 'pending'
    | 'paid'
    | 'failed'
    | 'expired'
    | 'canceled';

export type OrganizationInvoice = {
    orderId: string;
    planName: string;
    amount: string;
    billingPeriod: 'monthly' | 'yearly';
    billingPeriodLabel: string;
    status: OrganizationInvoiceStatus;
    statusLabel: string;
    paidAt: string | null;
    createdAt: string;
};

export type AdminCustomPlanRequest = {
    id: number;
    organizationName: string;
    requestedByName: string;
    requestedByEmail: string;
    requestedMaxStores: number | null;
    requestedMaxOwners: number | null;
    currentStoreCount: number;
    message: string | null;
    status: CustomPlanRequestStatus;
    statusLabel: string;
    reviewedByName: string | null;
    reviewedAt: string | null;
    createdAt: string;
};
