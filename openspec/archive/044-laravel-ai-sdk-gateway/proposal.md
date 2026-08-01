# 044 — Laravel AI SDK under Phoenix Gateway

**Issue:** [TXA-2750](https://txa-labs.youtrack.cloud/issue/TXA-2750)

## Decisión

Usar `laravel/ai` como motor de providers + tool-calling bajo `AiGateway`, sin sustituir:

- Tool registry Phoenix + RBAC (`AiToolAuthorizer`)
- Config plataforma (proveedor Google/OpenAI/local)
- Flag empresa `ai_enabled`
- Auditoría `AiInvocation`
- Modo local grounded (sin LLM)

## Piezas

| Componente | Rol |
|------------|-----|
| `OperationalAssistant` | Agent Laravel AI (`HasTools`, `MaxSteps`) |
| `PhoenixDomainTool` | Adapta `AiTool` → `Laravel\Ai\Contracts\Tool` |
| `LaravelAiAssistantRunner` | Orquesta prompt + Lab Gemini/OpenAI |
| `AiGateway::invokeAssistant` | Prefiere Laravel AI si hay provider configurado; si no, local |

## Proveedores

- UI plataforma: `google` | `openai` | `local`
- Laravel AI: `google` → `Lab::Gemini`, `openai` → `Lab::OpenAI`
- Keys: `GEMINI_API_KEY` / `GOOGLE_API_KEY`, `OPENAI_API_KEY`
