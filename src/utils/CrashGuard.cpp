#include "utils/CrashGuard.h"

#include "utils/Logger.h"

#include <chrono>
#include <csignal>
#include <exception>
#include <filesystem>
#include <fstream>
#include <iomanip>
#include <mutex>
#include <sstream>

#include <fmt/format.h>

#ifdef _WIN32
#include <Windows.h>
#include <DbgHelp.h>
#pragma comment(lib, "DbgHelp.lib")
#endif

namespace {
        constexpr std::size_t LOG_TAIL_BYTES = 50 * 1024;

        std::mutex crashMutex;
        bool handlingCrash = false;

        std::string buildTimestamp() {
                auto now = std::chrono::system_clock::now();
                auto time = std::chrono::system_clock::to_time_t(now);
                std::tm tm{};
        #if defined(_WIN32)
                localtime_s(&tm, &time);
        #else
                localtime_r(&time, &tm);
        #endif
                std::ostringstream oss;
                oss << std::put_time(&tm, "%Y%m%d_%H%M%S");
                return oss.str();
        }

        std::string readLogTail(const std::filesystem::path& logPath) {
                if (logPath.empty() || !std::filesystem::exists(logPath)) {
                        return {};
                }

                std::ifstream file(logPath, std::ios::binary);
                if (!file.is_open()) {
                        return {};
                }

                const auto size = file.seekg(0, std::ios::end).tellg();
                const auto start = size > static_cast<std::streampos>(LOG_TAIL_BYTES) ? size - static_cast<std::streampos>(LOG_TAIL_BYTES) : 0;
                file.seekg(start, std::ios::beg);

                std::string data(static_cast<std::size_t>(size - start), '\0');
                file.read(data.data(), data.size());
                return data;
        }

#ifdef _WIN32
        bool writeMiniDump(EXCEPTION_POINTERS* info, const std::filesystem::path& dumpPath) {
                HANDLE file = CreateFileW(dumpPath.wstring().c_str(), GENERIC_WRITE, FILE_SHARE_READ, nullptr, CREATE_ALWAYS, FILE_ATTRIBUTE_NORMAL, nullptr);
                if (file == INVALID_HANDLE_VALUE) {
                        return false;
                }

                MINIDUMP_EXCEPTION_INFORMATION exceptionInfo{};
                exceptionInfo.ThreadId = GetCurrentThreadId();
                exceptionInfo.ExceptionPointers = info;
                exceptionInfo.ClientPointers = FALSE;

                BOOL result = MiniDumpWriteDump(GetCurrentProcess(), GetCurrentProcessId(), file, MiniDumpNormal, info ? &exceptionInfo : nullptr, nullptr, nullptr);
                CloseHandle(file);
                return result == TRUE;
        }
#endif

        void writeCrashArtifacts(const std::string& reason, EXCEPTION_POINTERS* info) {
                std::lock_guard<std::mutex> lock(crashMutex);
                if (handlingCrash) {
                        return;
                }
                handlingCrash = true;

                Logger::instance().flush();

                const auto timestamp = buildTimestamp();

                const auto dumpDir = makePath("minidumps");
                const auto crashDir = makePath("logs");
                std::filesystem::create_directories(dumpDir);
                std::filesystem::create_directories(crashDir);

                std::filesystem::path dumpPath = dumpDir / fmt::format("crash_{}.dmp", timestamp);
#ifdef _WIN32
                if (writeMiniDump(info, dumpPath)) {
                        Logger::instance().error(fmt::format("MiniDump written to {}", dumpPath.string()));
                } else {
                        Logger::instance().error("Failed to write MiniDump");
                }
#else
                (void)info;
#endif

                std::filesystem::path crashLogPath = crashDir / fmt::format("crash_{}.log", timestamp);
                std::ofstream crashLog(crashLogPath, std::ios::out | std::ios::trunc);
                if (crashLog.is_open()) {
                        crashLog << "Reason: " << reason << "\n\n";
                        const auto logPath = makePath(Logger::instance().getLogFilePath());
                        const auto tail = readLogTail(logPath);
                        if (!tail.empty()) {
                                crashLog << "Log tail (" << logPath.string() << "):\n" << tail;
                        }
                }

                Logger::instance().fatal(fmt::format("Crash detected: {}", reason));
        }

#ifdef _WIN32
        LONG WINAPI sehHandler(EXCEPTION_POINTERS* info) {
                writeCrashArtifacts("Unhandled SEH exception", info);
                return EXCEPTION_EXECUTE_HANDLER;
        }
#endif

        void terminateHandler() {
                writeCrashArtifacts("std::terminate invoked", nullptr);
                std::abort();
        }

        void unexpectedHandler() {
                writeCrashArtifacts("std::unexpected invoked", nullptr);
                std::abort();
        }

        void signalHandler(int signal) {
                writeCrashArtifacts(fmt::format("Signal {} received", signal), nullptr);
                std::signal(signal, SIG_DFL);
                std::raise(signal);
        }

#ifdef _WIN32
        BOOL WINAPI consoleHandler(DWORD signal) {
                Logger::instance().warn(fmt::format("Console control event: {}", signal));
                Logger::instance().flush();
                return FALSE;
        }
#endif
} // namespace

void InstallCrashHandlers() {
#ifdef _WIN32
        SetUnhandledExceptionFilter(sehHandler);
        SetConsoleCtrlHandler(consoleHandler, TRUE);
#endif
        std::set_terminate(terminateHandler);
        std::set_unexpected(unexpectedHandler);
        std::signal(SIGABRT, signalHandler);
#ifdef SIGSEGV
        std::signal(SIGSEGV, signalHandler);
#endif
}

