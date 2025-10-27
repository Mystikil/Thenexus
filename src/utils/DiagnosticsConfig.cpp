#include "utils/DiagnosticsConfig.h"

#include <atomic>

namespace {

std::atomic_bool g_sqlTraceEnabled{false};
std::atomic_bool g_traceStartupEnabled{false};

} // namespace

namespace diagnostics {

bool isSqlTraceEnabled()
{
    return g_sqlTraceEnabled.load(std::memory_order_relaxed);
}

void setSqlTraceEnabled(bool enabled)
{
    g_sqlTraceEnabled.store(enabled, std::memory_order_relaxed);
}

bool isTraceStartupEnabled()
{
    return g_traceStartupEnabled.load(std::memory_order_relaxed);
}

void setTraceStartupEnabled(bool enabled)
{
    g_traceStartupEnabled.store(enabled, std::memory_order_relaxed);
}

} // namespace diagnostics

