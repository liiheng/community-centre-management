<?php
session_start();
require_once "includes/db.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user info from DB
$user_id = $_SESSION['user_id'];
$sql = "SELECT name, role FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($name, $role);
$stmt->fetch();
$stmt->close();
// ensure session has the user's display name for the header
$_SESSION['name'] = $name;

// Get all rooms from database
// Removed database query as requested

// header will be included inside the body for valid HTML structure
?>

<!DOCTYPE html>
<html>

<head>
    <title>Main Menu - Community Centre</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background-color: #f2f2f2;
        }

        .main-content {
            padding: 40px 20px;
            text-align: center;
        }

        .main-content h1 {
            margin-bottom: 10px;
        }

        .card-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
            margin-top: 30px;
        }

        .card-link {
            text-decoration: none;
            color: inherit;
        }

        .card {
            background-color: #fff;
            border-radius: 15px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 220px;
            height: 150px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            transition: transform 0.2s ease;
            text-align: center;
        }

        .card:hover {
            transform: scale(1.05);
            cursor: pointer;
            background-color: #f0f0f0;
        }

        .card i {
            font-size: 36px;
            margin-bottom: 10px;
            color: #007BFF;
        }

        .card p {
            font-size: 16px;
            margin: 0;
        }

        .venue-container {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .venue-card {
            position: relative;
            width: 300px;
            height: 200px;
            overflow: hidden;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .venue-card:hover {
            transform: translateY(-5px);
        }

        .venue-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform .4s ease;
        }

        .venue-card:hover img {
            transform: scale(1.1);
        }

        .venue-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.7);
            color: #fff;
            padding: 15px;
            transform: translateY(100%);
            transition: transform .4s ease;
        }

        .venue-card:hover .venue-overlay {
            transform: translateY(0);
        }

        /* Room Details Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
        }

        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 0;
            width: 80%;
            max-width: 800px;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            display: flex;
            max-height: 80vh;
        }

        .modal-image {
            width: 45%;
            background-size: cover;
            background-position: center;
            min-height: 400px;
        }

        .modal-details {
            width: 55%;
            padding: 30px;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            max-height: 80vh;
        }

        .modal-header {
            border-bottom: 2px solid #007BFF;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .modal-header h2 {
            color: #007BFF;
            margin: 0;
            font-size: 28px;
        }

        .modal-body {
            flex-grow: 1;
            overflow-y: auto;
            padding-right: 10px;
        }

        .room-info {
            margin-bottom: 20px;
        }

        .room-info h4 {
            color: #333;
            margin-bottom: 8px;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .room-info p, .room-info ul {
            color: #666;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .room-number {
            background: #007BFF;
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: bold;
            display: inline-block;
            margin-bottom: 15px;
        }

        .facilities-list {
            list-style: none;
            padding: 0;
        }

        .facilities-list li {
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }

        .facilities-list li:last-child {
            border-bottom: none;
        }

        .facilities-list i {
            color: #007BFF;
            margin-right: 10px;
            width: 20px;
        }

        .close-btn {
            position: absolute;
            top: 15px;
            right: 20px;
            background: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            font-size: 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.3s ease;
        }

        .close-btn:hover {
            background: #fff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .book-btn {
            background: #28a745;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            margin-top: 20px;
        }

        .book-btn:hover {
            background: #218838;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .modal-content {
                flex-direction: column;
                width: 95%;
                max-height: 90vh;
            }

            .modal-image, .modal-details {
                width: 100%;
            }

            .modal-image {
                min-height: 250px;
            }

            .venue-card {
                width: 280px;
            }
        }
    </style>
</head>

<body>

    <?php include 'includes/header.php'; ?>

    <div class="main-content">
        <h1>Welcome to the Community Activities Centre</h1>
        <p>You are logged in as <strong><?php echo ucfirst($role); ?></strong></p>

        <div class="card-container">
            <?php if ($role == 'member'): ?>
                <a href="member/view_activity.php" class="card-link">
                    <div class="card">
                        <i class="fas fa-calendar-check"></i>
                        <p>View & Register Activities</p>
                    </div>
                </a>
                <a href="member/joined_activity.php" class="card-link">
                    <div class="card">
                        <i class="fas fa-check-square"></i>
                        <p>View Joined Activities</p>
                    </div>
                </a>
                <a href="member/submit_feedback.php" class="card-link">
                    <div class="card">
                        <i class="fas fa-comments"></i>
                        <p>Submit Feedback</p>
                    </div>
                </a>

            <?php elseif ($role == 'organizer'): ?>
                <a href="organizer/create_activity.php" class="card-link">
                    <div class="card">
                        <i class="fas fa-plus-circle"></i>
                        <p>Create Activities</p>
                    </div>
                </a>
                <a href="organizer/my_activity.php" class="card-link">
                    <div class="card">
                        <i class="fas fa-list-check"></i>
                        <p>My Activities</p>
                    </div>
                </a>
                <a href="organizer/view_feedback.php" class="card-link">
                    <div class="card">
                        <i class="fas fa-comments"></i>
                        <p>View Feedback</p>
                    </div>
                </a>

            <?php elseif ($role == 'admin'): ?>
                <a href="admin/approve_activity.php" class="card-link">
                    <div class="card">
                        <i class="fas fa-check-circle"></i>
                        <p>Manage Activities</p>
                    </div>
                </a>
                <a href="admin/manage_accounts.php" class="card-link">
                    <div class="card">
                        <i class="fas fa-users-cog"></i>
                        <p>Manage Accounts</p>
                    </div>
                </a>
                <a href="admin/manage_feedback.php" class="card-link">
                    <div class="card">
                        <i class="fas fa-comments-dollar"></i>
                        <p>Manage Feedbacks</p>
                    </div>
                </a>
            <?php endif; ?>
        </div>

        <!-- ===== Venue Preview Section ===== -->
        <div class="venue-section" style="margin-top:40px;">
            <h2 style="text-align:center; margin-bottom:20px;">Our Venues</h2>
            <div class="venue-container">

                <!-- Main Hall -->
                <div class="venue-card" onclick="showRoomDetails('main-hall')">
                    <img src="images/main_hall.jpg" alt="Main Hall">
                    <div class="venue-overlay">
                        <h3>Main Hall</h3>
                        <p>A large multi-purpose hall for badminton, futsal, concerts, exhibitions, and community talks.</p>
                    </div>
                </div>

                <!-- Meeting Room -->
                <div class="venue-card" onclick="showRoomDetails('meeting-room')">
                    <img src="images/meeting_room.jpg" alt="Meeting Room">
                    <div class="venue-overlay">
                        <h3>Meeting Room</h3>
                        <p>Equipped with tables, chairs, and a projector. Suitable for meetings, workshops, and training sessions.</p>
                    </div>
                </div>

                <!-- Activity Room -->
                <div class="venue-card" onclick="showRoomDetails('activity-room')">
                    <img src="images/activity_room.jpg" alt="Activity Room">
                    <div class="venue-overlay">
                        <h3>Activity Room</h3>
                        <p>Flexible room for yoga, dance, art & craft, or children's learning programs.</p>
                    </div>
                </div>

            </div>
        </div>

        <!-- Room Details Modal -->
        <div id="roomModal" class="modal">
            <div class="modal-content">
                <button class="close-btn" onclick="closeModal()">&times;</button>
                <div id="modalImage" class="modal-image"></div>
                <div class="modal-details">
                    <div class="modal-header">
                        <h2 id="modalTitle"></h2>
                    </div>
                    <div class="modal-body">
                        <div class="room-info">
                            <h4><i class="fas fa-door-open"></i> Available Room</h4>
                            <p id="modalRooms"></p>
                        </div>
                        
                        <div class="room-info">
                            <h4><i class="fas fa-info-circle"></i> Description</h4>
                            <p id="modalDescription"></p>
                        </div>
                        
                        <div class="room-info">
                            <h4><i class="fas fa-cogs"></i> Facilities & Equipment</h4>
                            <ul id="modalFacilities" class="facilities-list"></ul>
                        </div>
                        
                        <div class="room-info">
                            <h4><i class="fas fa-users"></i> Capacity</h4>
                            <p id="modalCapacity"></p>
                        </div>
                    </div>
                    
                    <?php if ($role == 'organizer'): ?>
                        <button class="book-btn" onclick="bookRoom()">
                            <i class="fas fa-calendar-plus"></i> Create Activity Here
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php include 'includes/footer.php'; ?>
    </div>

    <script>
        // Room data with details
        const roomData = {
            'main-hall': {
                title: 'Main Hall',
                rooms: 'Main Hall: H01',
                description: 'Our spacious main hall is designed for large-scale activities and events. With high ceilings and flexible floor space, it can accommodate various sporting activities, cultural performances, exhibitions, and community gatherings. The hall features professional lighting and sound systems, making it ideal for concerts and presentations.',
                facilities: [
                    'Professional sound system with wireless microphones',
                    'LED stage lighting with dimmer controls',
                    'Retractable badminton nets (4 courts)',
                    'Futsal goal posts and court markings', 
                    'Projection screen and multimedia system',
                    'Air conditioning and ventilation',
                    'Storage space for equipment',
                    'Emergency exits and safety equipment'
                ],
                capacity: 'Up to 200 people for seated events, 150 for sports activities',
                image: 'images/main_hall.jpg'
            },
            'meeting-room': {
                title: 'Meeting Room',
                rooms: 'Meeting Room: M01, M02',
                description: 'A professional meeting space designed for corporate events, workshops, training sessions, and community meetings. The room features modern furniture and technology to support productive discussions and presentations.',
                facilities: [
                    'Conference table for 20 people',
                    'Ergonomic office chairs',
                    'HD projector and large screen',
                    'Whiteboard and flip chart stands',
                    'High-speed WiFi internet',
                    'Video conferencing equipment',
                    'Air conditioning',
                    'Coffee station with refreshment area'
                ],
                capacity: '20-25 people in boardroom setup, 40 people in theater style',
                image: 'images/meeting_room.jpg'
            },
            'activity-room': {
                title: 'Activity Room',
                rooms: 'Activity Room: A01, A02',
                description: 'A versatile space perfect for fitness classes, dance sessions, art workshops, and educational programs. The room features mirrors, flexible flooring, and storage for various activity equipment.',
                facilities: [
                    'Wall-to-wall mirrors for dance and fitness',
                    'Spring-loaded wooden dance floor',
                    'Yoga mats and exercise equipment storage',
                    'Sound system with Bluetooth connectivity',
                    'Adjustable LED lighting',
                    'Storage cabinets for art supplies',
                    'Washable surfaces for easy cleaning',
                    'First aid station and emergency equipment'
                ],
                capacity: '30 people for fitness classes, 25 for workshops, 15 for children programs',
                image: 'images/activity_room.jpg'
            }
        };

        function showRoomDetails(roomType) {
            const room = roomData[roomType];
            const modal = document.getElementById('roomModal');
            
            // Set modal content
            document.getElementById('modalTitle').textContent = room.title;
            document.getElementById('modalDescription').textContent = room.description;
            document.getElementById('modalRooms').textContent = room.rooms;
            document.getElementById('modalCapacity').textContent = room.capacity;
            document.getElementById('modalImage').style.backgroundImage = `url('${room.image}')`;
            
            // Set facilities
            const facilitiesList = document.getElementById('modalFacilities');
            facilitiesList.innerHTML = '';
            room.facilities.forEach(facility => {
                const li = document.createElement('li');
                li.innerHTML = `<i class="fas fa-check"></i> ${facility}`;
                facilitiesList.appendChild(li);
            });
            
            // Show modal
            modal.style.display = 'block';
        }

        function closeModal() {
            document.getElementById('roomModal').style.display = 'none';
        }

        function bookRoom() {
            // Redirect to create activity page for organizers
            window.location.href = 'organizer/create_activity.php';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('roomModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
    </script>

</body>

</html>