#include "common/diagnostics.h"

#include <atomic>

namespace diagnostics {
namespace {
std::atomic<bool> sqlTraceEnabled{false};
std::atomic<bool> traceStartupEnabled{false};
} // namespace

bool isSqlTraceEnabled()
{
    return sqlTraceEnabled.load();
}

void setSqlTraceEnabled(bool enabled)
{
    sqlTraceEnabled.store(enabled);
}

bool isTraceStartupEnabled()
{
    return traceStartupEnabled.load();
}

void setTraceStartupEnabled(bool enabled)
{
    traceStartupEnabled.store(enabled);
}

} // namespace diagnostics
