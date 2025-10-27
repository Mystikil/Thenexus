#pragma once

#include <atomic>
#include <filesystem>
#include <fstream>
#include <mutex>
#include <string>
#include <string_view>

#include <fmt/format.h>

enum class LogLevel {
        Trace = 0,
        Debug,
        Info,
        Warn,
        Error,
        Fatal,
};

class Logger {
        public:
                static Logger& instance();

                void setLogFile(const std::filesystem::path& path, std::uintmax_t maxBytes, std::size_t maxFiles);
                void setConsole(bool enabled);
                void setLevel(LogLevel level);

                void log(LogLevel level, std::string_view message);

                void trace(std::string_view message);
                void debug(std::string_view message);
                void info(std::string_view message);
                void warn(std::string_view message);
                void error(std::string_view message);
                void fatal(std::string_view message);

                std::filesystem::path getLogFilePath() const;
                void flush();

        private:
                Logger() = default;

                void rotateIfNeeded(std::size_t messageBytes);
                void write(std::string_view formattedMessage);
                static std::string levelToString(LogLevel level);
                static LogLevel parseLevelFromEnv(LogLevel fallback);

                mutable std::mutex mutex_;
                std::filesystem::path logFilePath_;
                std::ofstream logStream_;
                std::uintmax_t maxBytes_ = 0;
                std::size_t maxFiles_ = 0;
                LogLevel level_ = LogLevel::Info;
                bool consoleEnabled_ = true;
};

std::filesystem::path makePath(const std::filesystem::path& relative);

