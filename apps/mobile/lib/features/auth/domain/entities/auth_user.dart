class AuthUser {
  const AuthUser({
    required this.userId,
    required this.name,
    required this.email,
    required this.locations,
  });

  final int userId;
  final String name;
  final String email;
  final List<int> locations;

  factory AuthUser.fromJson(Map<String, dynamic> json) {
    return AuthUser(
      userId: json['user_id'] as int,
      name: json['name'] as String,
      email: json['email'] as String,
      locations: (json['locations'] as List<dynamic>).cast<int>(),
    );
  }
}
