#!/bin/bash

# PlayerProfit Betting Tracker Management Script
# Usage: ./run.sh [start|stop|restart|logs|status]

CONTAINER_NAME="playerprofit-betting-tracker"
PORT=8004

case "$1" in
    start)
        echo "🏆 Starting PlayerProfit Betting Tracker..."
        docker-compose up -d
        echo "✅ PlayerProfit Tracker is now running!"
        echo "🌐 Access your tracker at: http://localhost:$PORT"
        echo "📊 Dashboard: Account setup and bet tracking"
        echo "⚠️  Make sure to configure your account tier and size on first visit"
        ;;
    stop)
        echo "🛑 Stopping PlayerProfit Betting Tracker..."
        docker-compose down
        echo "✅ PlayerProfit Tracker stopped"
        ;;
    restart)
        echo "🔄 Restarting PlayerProfit Betting Tracker..."
        docker-compose down
        docker-compose up -d
        echo "✅ PlayerProfit Tracker restarted!"
        echo "🌐 Access at: http://localhost:$PORT"
        ;;
    logs)
        echo "📋 PlayerProfit Tracker Logs:"
        docker-compose logs -f
        ;;
    status)
        echo "📊 PlayerProfit Tracker Status:"
        if [ "$(docker ps -q -f name=$CONTAINER_NAME)" ]; then
            echo "✅ Running on port $PORT"
            echo "🌐 URL: http://localhost:$PORT"
            echo "📈 Container: $(docker ps --format "table {{.Status}}" -f name=$CONTAINER_NAME | tail -1)"
        else
            echo "❌ Not running"
            echo "💡 Use './run.sh start' to start the tracker"
        fi
        ;;
    *)
        echo "🏆 PlayerProfit Betting Tracker Management"
        echo "=========================================="
        echo "Usage: $0 {start|stop|restart|logs|status}"
        echo ""
        echo "Commands:"
        echo "  start   - Start the PlayerProfit tracker (port $PORT)"
        echo "  stop    - Stop the tracker"
        echo "  restart - Restart the tracker"
        echo "  logs    - View container logs"
        echo "  status  - Check if tracker is running"
        echo ""
        echo "🎯 Features:"
        echo "  • Pro & Standard account tiers"
        echo "  • Phase tracking (Phase 1 → Phase 2 → Funded)"
        echo "  • Risk management & violation detection"
        echo "  • Progress tracking with profit targets"
        echo "  • PlayerProfit-specific Discord reporting"
        echo ""
        echo "💡 First time setup:"
        echo "  1. Run: ./run.sh start"
        echo "  2. Visit: http://localhost:$PORT"
        echo "  3. Configure your account tier and size"
        echo "  4. Start tracking your betting progress!"
        ;;
esac
