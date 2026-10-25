<?php

namespace App\Http\Controllers\Reports;

use App\Actions\Reports\GenerateReport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\GenerateReportRequest;
use App\Models\Project;
use App\Models\Report;
use Illuminate\Http\Client\RequestException as HttpRequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use InvalidArgumentException;
use RuntimeException;
use Prism\Prism\Exceptions\PrismException;
use Throwable;

final class GenerateReportController extends Controller
{
    public function __invoke(GenerateReportRequest $request, Project $project, Report $report, GenerateReport $generate): RedirectResponse
    {
        abort_unless($report->project_id === $project->id, 404);

        try {
            $language = $request->validated('language', str_replace('_', '-', app()->getLocale()));
            $generate->handle($request->user(), $report, $language);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['ai_generation' => __($exception->getMessage())]);
        } catch (Throwable $exception) {
            // Provider exceptions may embed prompts, responses or credentials.
            // Log only fixed reason codes and the exception class: never report()
            // the original exception or include its message / trace in the logs.
            $status = $this->safeProviderHttpStatus($exception);
            $reason = $this->safeFailureReason($exception, $status);
            Log::warning('report_ai_generation_failed', [
                'reason' => $reason,
                'provider_http_status' => $status,
                'exception_class' => $exception::class,
                'project_id' => $project->id,
                'report_id' => $report->id,
            ]);

            $message = match ($reason) {
                'output_truncated' => 'The AI response was cut off. Increase DEEPSEEK_REPORT_MAX_COMPLETION_TOKENS before trying again.',
                'unsupported_finish_reason' => 'DeepSeek ended generation without a usable report. Check the report model and DEEPSEEK_REPORT_MAX_COMPLETION_TOKENS.',
                'invalid_output' => 'The AI returned an empty or invalid report. Try again later.',
                'insufficient_balance' => 'DeepSeek API account balance is insufficient. Check your provider dashboard.',
                'invalid_api_key' => 'DeepSeek rejected the API credentials. Check the provider configuration.',
                'model_or_endpoint_not_found' => 'The AI model or API endpoint was not found. Check the provider configuration.',
                'rate_limited', 'provider_unavailable', 'timeout' => 'The AI provider is temporarily unavailable. Try again later.',
                default => 'Could not generate the report. See server log code report_ai_generation_failed for diagnostics.',
            };

            throw ValidationException::withMessages(['ai_generation' => __($message)]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Report generated. Review it before sharing.')]);

        return to_route('reports.show', [$project, $report]);
    }

    private function safeProviderHttpStatus(Throwable $exception): ?int
    {
        // Prism's model error message and HTTP response body can contain source text.
        // Inspect only numeric status metadata; never log the exception itself.
        if (! $exception instanceof PrismException) {
            return null;
        }

        $previous = $exception->getPrevious();
        $possible = [
            $exception->getCode(),
            $previous instanceof HttpRequestException ? $previous->response->status() : null,
        ];
        foreach ($possible as $status) {
            if (is_int($status) && $status >= 400 && $status <= 599) {
                return $status;
            }
        }

        return null;
    }

    private function safeFailureReason(Throwable $exception, ?int $status): string
    {
        // Exact comparison with messages emitted by our own generator.
        // Never include an arbitrary provider-supplied message in a log or response.
        if ($exception instanceof RuntimeException) {
            if ($exception->getMessage() === __('The AI report was truncated. Increase the maximum token limit.')) {
                return 'output_truncated';
            }
            if ($exception->getMessage() === __('AI returned an empty or excessively long report.')) {
                return 'invalid_output';
            }
        }

        if ($exception instanceof PrismException) {
            // Prism v0.100.1 throws this fixed message for several non-Stop
            // finish reasons, including length and content_filter. Do not
            // wrongly claim that it was definitely a token limit.
            if ($exception->getMessage() === 'DeepSeek: unknown finish reason') {
                return 'unsupported_finish_reason';
            }

            // Prism currently wraps DeepSeek HTTP 402 without preserving its status.
            if ($status === 402 || str_starts_with($exception->getMessage(), 'DeepSeek Insufficient Balance:')) {
                return 'insufficient_balance';
            }

            return match ($status) {
                400, 413, 422 => 'provider_rejected_request',
                401 => 'invalid_api_key',
                403 => 'provider_access_denied',
                404 => 'model_or_endpoint_not_found',
                408, 504 => 'timeout',
                429 => 'rate_limited',
                500, 502, 503 => 'provider_unavailable',
                default => 'provider_error_unknown_status',
            };
        }

        return 'application_error';
    }
}
