<?php

namespace Database\Seeders\Support;

use App\Models\FormDefinition;
use App\Models\FormVersion;
use App\Models\ReportTemplate;
use App\Models\ReportTemplateVersion;
use App\Models\User;

final class DemoDesignDraftVersions
{
    public static function ensureFormDraft(FormDefinition $form, FormVersion $published, User $creator): FormVersion
    {
        return FormVersion::query()->updateOrCreate(
            [
                'form_definition_id' => $form->id,
                'version' => $published->version + 1,
            ],
            [
                'status' => 'draft',
                'schema' => $published->schema,
                'created_by' => $creator->id,
            ],
        );
    }

    public static function ensureReportDraft(
        ReportTemplate $template,
        ReportTemplateVersion $published,
        User $creator,
    ): ReportTemplateVersion {
        return ReportTemplateVersion::query()->updateOrCreate(
            [
                'report_template_id' => $template->id,
                'version' => $published->version + 1,
            ],
            [
                'status' => 'draft',
                'components' => $published->components,
                'page_settings' => $published->page_settings,
                'created_by' => $creator->id,
            ],
        );
    }
}
