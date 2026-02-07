<?php
require_once 'session_check.php';
require_once 'config.php';
require_once 'includes/db_connect.php';

// Check if user is admin
if (!has_role('admin')) {
    redirect('dashboard.php');
}

$page_title = 'AI Menu Suggestions';

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="page-header">
        <h1>Menu Generation</h1>
        <p>Get intelligent menu suggestions based on sales data and trends</p>
    </div>
    
    <div class="suggestion-controls">
        <div class="control-group">
            <label>Analysis Period:</label>
            <select id="analysisPeriod">
                <option value="7">Last 7 Days</option>
                <option value="30" selected>Last 30 Days</option>
                <option value="90">Last 90 Days</option>
            </select>
        </div>
        
        <div class="control-group">
            <label>Category:</label>
            <select id="categoryFilter">
                <option value="all">All Categories</option>
                <option value="coffee">Coffee</option>
                <option value="food">Food</option>
                <option value="dessert">Dessert</option>
                <option value="beverage">Beverage</option>
            </select>
        </div>
        
        <button onclick="generateSuggestions()" class="btn-primary">Generate Suggestions</button>
    </div>
    
    <div class="insights-grid">
        <div class="insight-card">
            <h3>Top Performers</h3>
            <div id="topPerformers" class="insight-content">
                <p class="loading">Click "Generate Suggestions" to analyze</p>
            </div>
        </div>
        
        <div class="insight-card">
            <h3>Underperforming Items</h3>
            <div id="underperformers" class="insight-content">
                <p class="loading">Click "Generate Suggestions" to analyze</p>
            </div>
        </div>
        
        <div class="insight-card">
            <h3>Trending Combinations</h3>
            <div id="combinations" class="insight-content">
                <p class="loading">Click "Generate Suggestions" to analyze</p>
            </div>
        </div>
        
        <div class="insight-card">
            <h3>Seasonal Opportunities</h3>
            <div id="seasonal" class="insight-content">
                <p class="loading">Click "Generate Suggestions" to analyze</p>
            </div>
        </div>
    </div>
    
    <div class="suggestions-section">
        <h3>AI-Generated Menu Suggestions</h3>
        <div id="suggestionsContent" class="suggestions-list">
            <div class="empty-state">
                <p>Generate suggestions to see AI-powered menu recommendations</p>
            </div>
        </div>
    </div>
</main>

<style>
.suggestion-controls {
    background: white;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    gap: 20px;
    align-items: flex-end;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.control-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.control-group label {
    font-size: 14px;
    font-weight: 500;
    color: #666;
}

.control-group select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.insights-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.insight-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.insight-card h3 {
    margin-bottom: 15px;
    color: #96715e;
    font-size: 18px;
}

.insight-content {
    min-height: 150px;
}

.loading {
    color: #999;
    text-align: center;
    padding: 40px 0;
}

.suggestions-section {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.suggestions-section h3 {
    margin-bottom: 20px;
    color: #96715e;
}

.suggestions-list {
    display: grid;
    gap: 15px;
}

.suggestion-item {
    padding: 15px;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    background: #fafafa;
}

.suggestion-item h4 {
    color: #96715e;
    margin-bottom: 10px;
}

.suggestion-item p {
    color: #666;
    line-height: 1.6;
}

.suggestion-item p strong {
    color: #96715e;
    font-weight: 600;
}

.suggestion-meta {
    display: flex;
    gap: 15px;
    margin-top: 10px;
    font-size: 14px;
}

.suggestion-meta span {
    color: #999;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #999;
}

.item-list {
    list-style: none;
    padding: 0;
}

.item-list li {
    padding: 8px 0;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
}

.item-list li:last-child {
    border-bottom: none;
}
</style>

<script>
function generateSuggestions() {
    const period = document.getElementById('analysisPeriod').value;
    const category = document.getElementById('categoryFilter').value;

    // Show loading state
    document.getElementById('topPerformers').innerHTML = '<p class="loading">Analyzing...</p>';
    document.getElementById('underperformers').innerHTML = '<p class="loading">Analyzing...</p>';
    document.getElementById('combinations').innerHTML = '<p class="loading">Analyzing...</p>';
    document.getElementById('seasonal').innerHTML = '<p class="loading">Analyzing...</p>';
    document.getElementById('suggestionsContent').innerHTML = '<div class="empty-state"><p>Generating AI suggestions...</p></div>';

    // Use predefined menu suggestions
    const predefinedSuggestions = [
        {
            title: 'Spanish Latte',
            description: 'Espresso, Condensed Milk, Full Cream Milk',
            price: 175.00,
            category: 'Iced Coffee',
            priority: 'High',
            impact: 'High Sales Potential'
        },
        {
            title: 'Avena Con Leche',
            description: 'Espresso, Condensed Milk, Oat Milk',
            price: 195.00,
            category: 'Iced Coffee',
            priority: 'High',
            impact: 'Trending Ingredient'
        },
        {
            title: 'Tres Leches',
            description: 'Espresso, Condensed Milk, Heavy Cream, Full Cream Milk',
            price: 195.00,
            category: 'Iced Coffee',
            priority: 'High',
            impact: 'Premium Offering'
        },
        {
            title: 'Tablea Mocha',
            description: 'Espresso, Tablea, Condensed Milk, Full Cream Milk',
            price: 195.00,
            category: 'Iced Coffee',
            priority: 'Medium',
            impact: 'Local Flavor'
        },
        {
            title: 'Blueberry Oat Latte',
            description: 'Espresso, Blueberry Jam, Oat Milk',
            price: 185.00,
            category: 'Iced Coffee',
            priority: 'Medium',
            impact: 'Health-Conscious'
        },
        {
            title: 'Butterscotch Macchiato',
            description: 'Espresso, Butterscotch Sauce, Full Cream Milk, Cold Foam',
            price: 195.00,
            category: 'Iced Coffee',
            priority: 'High',
            impact: 'Popular Flavor'
        },
        {
            title: 'Caramel Macchiato',
            description: 'Espresso, Caramel Sauce, Full Cream Milk, Cold Foam',
            price: 195.00,
            category: 'Iced Coffee',
            priority: 'High',
            impact: 'Classic Favorite'
        },
        {
            title: 'White Chocolate Latte',
            description: 'Espresso, White Chocolate Sauce, Full Cream Milk',
            price: 185.00,
            category: 'Iced Coffee',
            priority: 'Medium',
            impact: 'Indulgent Option'
        },
        {
            title: 'French Vanilla Latte',
            description: 'Espresso, French Vanilla Syrup, Full Cream Milk',
            price: 185.00,
            category: 'Iced Coffee',
            priority: 'Medium',
            impact: 'Timeless Classic'
        }
    ];

    // Filter suggestions based on category if specified
    let filteredSuggestions = predefinedSuggestions;
    if (category !== 'all') {
        filteredSuggestions = predefinedSuggestions.filter(suggestion =>
            suggestion.category.toLowerCase().includes(category.toLowerCase())
        );
    }

    // Simulate API delay
    setTimeout(() => {
        // Mock insights data
        const mockInsights = {
            top_performers: [
                { name: 'Tablea Mocha', sales: 25 },
                { name: 'Butterscotch Macchiato', sales: 22 },
                { name: 'Spanish Latte', sales: 20 }
            ],
            underperformers: [
                { name: 'Blueberry Oat Latte', sales: 3 },
                { name: 'White Chocolate Latte', sales: 5 }
            ],
            combinations: 'Popular combinations include Tablea Mocha with pastries and Butterscotch Macchiato with desserts.',
            seasonal: 'Seasonal opportunities: Holiday-themed drinks with local ingredients during December.'
        };

        // Update insights
        updateInsights(mockInsights);

        // Update suggestions
        updateSuggestions(filteredSuggestions);
    }, 1000);
}

function updateInsights(insights) {
    // Top Performers
    if (insights.top_performers.length > 0) {
        document.getElementById('topPerformers').innerHTML = `
            <ul class="item-list">
                ${insights.top_performers.map(item => `
                    <li>
                        <span>${item.name}</span>
                        <strong>${item.sales} sold</strong>
                    </li>
                `).join('')}
            </ul>
        `;
    } else {
        document.getElementById('topPerformers').innerHTML = '<p class="loading">No data available</p>';
    }
    
    // Underperformers
    if (insights.underperformers.length > 0) {
        document.getElementById('underperformers').innerHTML = `
            <ul class="item-list">
                ${insights.underperformers.map(item => `
                    <li>
                        <span>${item.name}</span>
                        <strong>${item.sales} sold</strong>
                    </li>
                `).join('')}
            </ul>
        `;
    } else {
        document.getElementById('underperformers').innerHTML = '<p class="loading">No data available</p>';
    }
    
    // Combinations
    document.getElementById('combinations').innerHTML = insights.combinations || '<p class="loading">Not enough data for analysis</p>';
    
    // Seasonal
    document.getElementById('seasonal').innerHTML = insights.seasonal || '<p class="loading">No seasonal insights available</p>';
}

function updateSuggestions(suggestions) {
    if (suggestions.length === 0) {
        document.getElementById('suggestionsContent').innerHTML = '<div class="empty-state"><p>No suggestions available at this time.</p></div>';
        return;
    }

    document.getElementById('suggestionsContent').innerHTML = suggestions.map(suggestion => `
        <div class="suggestion-item">
            <h4>${suggestion.title}</h4>
            <p>${suggestion.description}</p>
            <p><strong>Price: ₱${suggestion.price.toFixed(2)}</strong></p>
            <div class="suggestion-meta">
                <span>Priority: ${suggestion.priority}</span>
                <span>Impact: ${suggestion.impact}</span>
                <button class="btn-create" onclick="createFromSuggestion('${suggestion.title}', '${suggestion.description}', ${suggestion.price}, '${suggestion.category}')">Create Product</button>
            </div>
        </div>
    `).join('');
}

function createFromSuggestion(title, description, price, category) {
    // Redirect to products page with pre-filled parameters
    const params = new URLSearchParams({
        name: title,
        description: description,
        price: price,
        category: category,
        from_suggestion: '1'
    });
    window.location.href = 'products.php?' + params.toString();
}
</script>

<?php include 'includes/footer.php'; ?>
