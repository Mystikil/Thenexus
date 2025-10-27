#ifndef THENEXUS_COMMON_DIAGNOSTICS_H
#define THENEXUS_COMMON_DIAGNOSTICS_H

namespace diagnostics {

bool isSqlTraceEnabled();
void setSqlTraceEnabled(bool enabled);

bool isTraceStartupEnabled();
void setTraceStartupEnabled(bool enabled);

} // namespace diagnostics

#endif // THENEXUS_COMMON_DIAGNOSTICS_H
