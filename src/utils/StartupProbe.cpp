#include "utils/StartupProbe.h"

#include "utils/Logger.h"

#include <atomic>
#include <mutex>
#include <thread>

#include <fmt/format.h>

namespace {

        std::mutex g_probeMutex;
        std::string g_currentPhase;
        std::chrono::steady_clock::time_point g_phaseStart;
        std::chrono::steady_clock::time_point g_lastProgress;
        std::chrono::steady_clock::time_point g_lastWarning;
        bool g_hasPhase = false;

        std::atomic_bool g_running{false};
        std::thread g_watchdogThread;
        std::chrono::milliseconds g_watchdogThreshold{std::chrono::milliseconds(10000)};

        constexpr std::chrono::seconds kWatchdogPollInterval{2};
        constexpr std::chrono::seconds kWatchdogRepeatInterval{10};

        void logPhaseStart(const std::string& phase) {
                Logger::instance().info(fmt::format("PHASE {} start", phase));
                Logger::instance().flush();
        }

        void logPhaseCompletion(const std::string& phase, std::chrono::milliseconds duration) {
                Logger::instance().info(fmt::format("PHASE {} done ({} ms)", phase, duration.count()));
                Logger::instance().flush();
        }

} // namespace

void StartupProbe::initialize() {
        bool expected = false;
        if (!g_running.compare_exchange_strong(expected, true)) {
                return;
        }

        {
                std::lock_guard<std::mutex> lock(g_probeMutex);
                g_lastProgress = std::chrono::steady_clock::now();
                g_lastWarning = g_lastProgress;
        }

        g_watchdogThread = std::thread(&StartupProbe::watchdogLoop);
}

void StartupProbe::shutdown() {
        bool expected = true;
        if (!g_running.compare_exchange_strong(expected, false)) {
                return;
        }

        if (g_watchdogThread.joinable()) {
                g_watchdogThread.join();
        }
}

void StartupProbe::setWatchdogThreshold(std::chrono::milliseconds threshold) {
        if (threshold <= std::chrono::milliseconds::zero()) {
                threshold = std::chrono::milliseconds(10000);
        }
        g_watchdogThreshold = threshold;
}

void StartupProbe::mark(const char* phase) {
        const auto now = std::chrono::steady_clock::now();
        std::string previousPhase;
        std::chrono::steady_clock::time_point previousStart{};
        bool hadPrevious = false;

        const bool startNewPhase = phase != nullptr && phase[0] != '\0';
        std::string nextPhase;
        if (startNewPhase) {
                nextPhase = phase;
        }

        {
                std::lock_guard<std::mutex> lock(g_probeMutex);
                if (g_hasPhase) {
                        previousPhase = g_currentPhase;
                        previousStart = g_phaseStart;
                        hadPrevious = true;
                }

                g_lastProgress = now;

                if (startNewPhase) {
                        g_currentPhase = nextPhase;
                        g_phaseStart = now;
                        g_hasPhase = true;
                } else {
                        g_currentPhase.clear();
                        g_hasPhase = false;
                }
        }

        if (hadPrevious && !previousPhase.empty()) {
                const auto duration = std::chrono::duration_cast<std::chrono::milliseconds>(now - previousStart);
                logPhaseCompletion(previousPhase, duration);
        }

        if (startNewPhase && !nextPhase.empty()) {
                logPhaseStart(nextPhase);
        }
}

void StartupProbe::watchdogLoop() {
        while (g_running.load(std::memory_order_relaxed)) {
                std::this_thread::sleep_for(kWatchdogPollInterval);
                if (!g_running.load(std::memory_order_relaxed)) {
                        break;
                }

                std::string phase;
                std::chrono::steady_clock::time_point lastProgress;
                {
                        std::lock_guard<std::mutex> lock(g_probeMutex);
                        phase = g_currentPhase;
                        lastProgress = g_lastProgress;
                }

                if (phase.empty()) {
                        continue;
                }

                const auto now = std::chrono::steady_clock::now();
                const auto stall = std::chrono::duration_cast<std::chrono::milliseconds>(now - lastProgress);
                if (stall < g_watchdogThreshold) {
                        std::lock_guard<std::mutex> lock(g_probeMutex);
                        g_lastWarning = now;
                        continue;
                }

                bool shouldLog = false;
                {
                        std::lock_guard<std::mutex> lock(g_probeMutex);
                        if ((now - g_lastWarning) >= kWatchdogRepeatInterval) {
                                g_lastWarning = now;
                                shouldLog = true;
                        }
                }

                if (shouldLog) {
                        Logger::instance().warn(fmt::format("WATCHDOG: stalled at {} ({} ms since last progress)", phase, stall.count()));
                        Logger::instance().flush();
                }
        }
}

