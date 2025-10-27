#include "otpch.h"
#include "configmanager.h"

int main() {
        (void)fmt::format(FMT_STRING("{}"), ConfigManager::ENABLE_REPUTATION_SYSTEM);
        (void)fmt::format(FMT_STRING("{}"), ConfigManager::MYSQL_HOST);
        (void)fmt::format(FMT_STRING("{}"), ConfigManager::SQL_PORT);
        return 0;
}
