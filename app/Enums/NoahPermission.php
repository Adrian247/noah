<?php

namespace App\Enums;

enum NoahPermission: string
{
    case CatalogManage = 'catalog.manage';
    case CatalogView = 'catalog.view';
    case AssetsManage = 'assets.manage';
    case AssetsView = 'assets.view';
    case DesignForms = 'design.forms';
    case DesignFormsView = 'design.forms.view';
    case DesignReports = 'design.reports';
    case DesignReportsView = 'design.reports.view';
    case DesignWorkflows = 'design.workflows';
    case DesignWorkflowsView = 'design.workflows.view';
    case RoutinesAssign = 'routines.assign';
    case RoutinesExecute = 'routines.execute';
    case RoutinesValidate = 'routines.validate';
    case CostsView = 'costs.view';
    case BillingDraft = 'billing.draft';
    case BillingIssue = 'billing.issue';
    case BillingSettings = 'billing.settings';
    case AuditView = 'audit.view';
    case CompanyUsersManage = 'company.users.manage';
    case CatalogSuppliersManage = 'catalog.suppliers.manage';
    case CatalogSuppliersView = 'catalog.suppliers.view';
    case SitesView = 'sites.view';
    case SitesManage = 'sites.manage';
    case ClientsManage = 'clients.manage';
    case ClientsView = 'clients.view';
    case BillingDraftEdit = 'billing.draft.edit';
    case PortalInvoicesView = 'portal.invoices.view';
    case PortalInvoicesDownload = 'portal.invoices.download';
    case PortalRoutinesView = 'portal.routines.view';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
